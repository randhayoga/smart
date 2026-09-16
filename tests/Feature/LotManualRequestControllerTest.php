<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Subcategory;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\TbProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for LotManualRequestController handling specific lot manual requests.
 */
class LotManualRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): AdmUser
    {
        return AdmUser::factory()->create();
    }

    private function createRequester(): AdmUser
    {
        return AdmUser::factory()->create([
            'name' => 'Budi Santoso',
        ]);
    }

    private function createConsumableBarang(): Barang
    {
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        return Barang::factory()->create(['subcategory_id' => $subcategory->id]);
    }

    private function createConsumableLot(Barang $barang, int $quantity = 50, ?string $receiptDate = null): Lot
    {
        $location = Location::firstOrCreate(['name' => 'Gudang Konsumabel'], ['full_name' => 'Gudang Konsumabel']);
        return Lot::factory()->create([
            'barang_id' => $barang->id,
            'location_id' => $location->id,
            'initial_quantity' => $quantity,
            'current_quantity' => $quantity,
            'date_of_receipt' => $receiptDate ?? now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_lot_manual_request(): void
    {
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 10);

        $this->post(route('smart.inventory.lots.manual-request', $lot->id))->assertRedirect();
    }

    public function test_can_create_specific_lot_manual_request_with_corporate_utilization(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 30);

        $note = 'Kebutuhan ATK departemen operasional';

        $response = $this->actingAs($admin)
            ->from('/smart/inventory/stok-habis-pakai')
            ->post(route('smart.inventory.lots.manual-request', $lot->id), [
                'user_id' => $requester->id,
                'request_date' => '2026-09-15',
                'utilization' => 'corporate',
                'org_id' => $orgchart->id,
                'quantity' => 10,
                'note' => $note,
            ]);

        $response->assertRedirect('/smart/inventory/stok-habis-pakai');
        $response->assertSessionHas('success', 'Permintaan manual berhasil dicatat.');

        // 1. Lot quantity deducted
        $this->assertEquals(20, $lot->fresh()->current_quantity);

        // 2. SmartRequest created with status success
        $smartReq = SmartRequest::where('user_id', $requester->id)->latest('id')->first();
        $this->assertNotNull($smartReq);
        $this->assertEquals('success', $smartReq->status);
        $this->assertEquals($admin->id, $smartReq->approver_id);
        $this->assertEquals('corporate', $smartReq->utilization);
        $this->assertEquals($orgchart->id, $smartReq->org_id);
        $this->assertEquals($note, $smartReq->reasoning);

        // 3. RequestItem created with status fulfilled
        $item = RequestItem::where('request_id', $smartReq->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(10, $item->quantity_requested);
        $this->assertEquals('fulfilled', $item->status);
        $this->assertEquals($barang->id, $item->barang_id);

        // 4. RequestFulfillment created with timestamps
        $fulfillment = RequestFulfillment::where('request_item_id', $item->id)->first();
        $this->assertNotNull($fulfillment);
        $this->assertEquals($lot->id, $fulfillment->lot_id);
        $this->assertEquals(10, $fulfillment->quantity_fulfilled);
        $this->assertEquals('2026-09-15', $fulfillment->assigned_at->toDateString());
        $this->assertEquals('2026-09-15', $fulfillment->confirmed_at->toDateString());
        $this->assertEquals('2026-09-15', $fulfillment->completed_at->toDateString());

        // 5. InventoryLog created with stock_out and negative quantity_change
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $requester->id,
            'action_type' => 'stock_out',
            'quantity_change' => -10,
            'note' => "Permintaan untuk Corporate {$orgchart->org_name}, dengan catatan \"{$note}\"",
        ]);

        $log = \App\Models\Inventory\InventoryLog::where('lot_id', $lot->id)->latest('id')->first();
        $this->assertEquals('2026-09-15', $log->created_at->toDateString());

        // 6. RequestStatusLog created
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $smartReq->id,
            'status_from' => 'draft',
            'status_to' => 'success',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_can_create_specific_lot_manual_request_with_project_utilization(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $project = TbProject::factory()->create();
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 25);

        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'project',
            'project_id' => $project->id_project,
            'quantity' => 15,
            'note' => 'Material pendukung proyek site',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(10, $lot->fresh()->current_quantity);

        $smartReq = SmartRequest::where('user_id', $requester->id)->latest('id')->first();
        $this->assertEquals('project', $smartReq->utilization);
        $this->assertEquals($project->id_project, $smartReq->project_id);
        $this->assertNull($smartReq->org_id);
    }

    public function test_cannot_create_specific_lot_request_when_quantity_exceeds_lot_stock(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 10);

        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 15,
            'note' => 'Melebihi stok',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('quantity');

        $this->assertEquals(10, $lot->fresh()->current_quantity);
        $this->assertDatabaseMissing('requests', ['reasoning' => 'Melebihi stok']);
    }

    public function test_cannot_create_specific_lot_request_for_non_consumable_lot(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();

        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $nonConsumableBarang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $lot = Lot::factory()->create([
            'barang_id' => $nonConsumableBarang->id,
            'current_quantity' => 10,
        ]);

        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 2,
            'note' => 'Bukan habis pakai',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('quantity');
        $this->assertEquals(10, $lot->fresh()->current_quantity);
    }

    public function test_can_create_manual_request_with_null_note(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();

        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 30);

        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 5,
            'note' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(25, $lot->fresh()->current_quantity);

        $smartReq = SmartRequest::where('user_id', $requester->id)->latest('id')->first();
        $this->assertNotNull($smartReq);
        $this->assertNull($smartReq->reasoning);

        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $requester->id,
            'action_type' => 'stock_out',
            'quantity_change' => -5,
            'note' => "Permintaan untuk Corporate {$orgchart->org_name}, dengan catatan \"-\"",
        ]);
    }

    public function test_validation_requires_valid_request_date(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 30);

        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot->id), [
            'user_id' => $requester->id,
            'request_date' => null,
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('request_date');
    }
}
