<?php

namespace Tests\Feature;

use App\Models\User;
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
 * Feature tests for BarangManualRequestController managing FIFO manual requests on consumable barangs.
 */
class BarangManualRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create();
    }

    private function createRequester(): User
    {
        return User::factory()->create([
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

    public function test_unauthenticated_user_cannot_access_barang_manual_request(): void
    {
        $barang = $this->createConsumableBarang();

        $this->post(route('smart.inventory.barangs.manual-request', $barang->id))->assertRedirect();
    }

    public function test_cannot_create_request_for_non_consumable_barang(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();

        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $nonConsumableBarang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $nonConsumableBarang->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 5,
            'note' => 'Bukan habis pakai',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('quantity');
    }

    public function test_can_create_non_specific_manual_request_fifo_single_lot(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 40, now()->subDays(10));

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 25,
            'note' => 'Permintaan non-spesifik 1 LOT',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(15, $lot->fresh()->current_quantity);

        $smartReq = SmartRequest::where('user_id', $requester->id)->latest('id')->first();
        $this->assertEquals('success', $smartReq->status);

        $item = RequestItem::where('request_id', $smartReq->id)->first();
        $this->assertEquals(25, $item->quantity_requested);

        $fulfillments = RequestFulfillment::where('request_item_id', $item->id)->get();
        $this->assertCount(1, $fulfillments);
        $this->assertEquals($lot->id, $fulfillments->first()->lot_id);
        $this->assertEquals(25, $fulfillments->first()->quantity_fulfilled);
        $this->assertEquals('2026-09-15', $fulfillments->first()->assigned_at->toDateString());
        $this->assertEquals('2026-09-15', $fulfillments->first()->confirmed_at->toDateString());
        $this->assertEquals('2026-09-15', $fulfillments->first()->completed_at->toDateString());

        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $requester->id,
            'quantity_change' => -25,
        ]);

        $log = \App\Models\Inventory\InventoryLog::where('lot_id', $lot->id)->latest('id')->first();
        $this->assertEquals('2026-09-15', $log->created_at->toDateString());
    }

    public function test_can_create_non_specific_manual_request_fifo_multiple_lots(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();

        $oldLot = $this->createConsumableLot($barang, 20, now()->subDays(20));
        $midLot = $this->createConsumableLot($barang, 30, now()->subDays(10));
        $newLot = $this->createConsumableLot($barang, 50, now()->subDays(2));

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 35,
            'note' => 'Permintaan multi LOT FIFO',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(0, $oldLot->fresh()->current_quantity);
        $this->assertEquals(15, $midLot->fresh()->current_quantity);
        $this->assertEquals(50, $newLot->fresh()->current_quantity);

        $smartReq = SmartRequest::where('user_id', $requester->id)->latest('id')->first();
        $item = RequestItem::where('request_id', $smartReq->id)->first();
        $this->assertEquals(35, $item->quantity_requested);

        $fulfillments = RequestFulfillment::where('request_item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(2, $fulfillments);
        $this->assertEquals($oldLot->id, $fulfillments[0]->lot_id);
        $this->assertEquals(20, $fulfillments[0]->quantity_fulfilled);
        $this->assertEquals('2026-09-15', $fulfillments[0]->assigned_at->toDateString());
        $this->assertEquals($midLot->id, $fulfillments[1]->lot_id);
        $this->assertEquals(15, $fulfillments[1]->quantity_fulfilled);
        $this->assertEquals('2026-09-15', $fulfillments[1]->assigned_at->toDateString());
    }

    public function test_cannot_create_non_specific_request_when_quantity_exceeds_total_stock(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();

        $this->createConsumableLot($barang, 15, now()->subDays(10));
        $this->createConsumableLot($barang, 10, now()->subDays(5));

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 30,
            'note' => 'Terlalu banyak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseMissing('requests', ['reasoning' => 'Terlalu banyak']);
    }

    public function test_validation_requires_org_id_when_utilization_is_corporate(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $barang = $this->createConsumableBarang();
        $this->createConsumableLot($barang, 20);

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'corporate',
            'org_id' => null,
            'quantity' => 5,
            'note' => 'Tanpa departemen',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('org_id');
    }

    public function test_validation_requires_project_id_when_utilization_is_project(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $barang = $this->createConsumableBarang();
        $this->createConsumableLot($barang, 20);

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
            'request_date' => '2026-09-15',
            'utilization' => 'project',
            'project_id' => null,
            'quantity' => 5,
            'note' => 'Tanpa project',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('project_id');
    }

    public function test_validation_requires_valid_request_date(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();
        $this->createConsumableLot($barang, 20);

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
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
