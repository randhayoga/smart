<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Subcategory;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for ConsumableManualRequestController handling manual stock deductions.
 */
class ConsumableManualRequestControllerTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_manual_request_endpoints(): void
    {
        $barang = $this->createConsumableBarang();
        $lot = $this->createConsumableLot($barang, 10);

        $this->post(route('smart.inventory.barangs.manual-request', $barang->id))->assertRedirect();
        $this->post(route('smart.inventory.lots.manual-request', $lot->id))->assertRedirect();
        $this->getJson(route('smart.inventory.consumables.request-options'))->assertUnauthorized();
    }

    public function test_options_endpoint_returns_users_departments_and_projects(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create(['org_name' => 'IT Infrastructure']);
        $project = TbProject::factory()->create(['project_name' => 'SMART Project']);

        $response = $this->actingAs($admin)->getJson(route('smart.inventory.consumables.request-options'));
        $response->assertOk();
        $response->assertJsonStructure([
            'users' => [['id', 'name', 'employee_id']],
            'departments' => [['id', 'name']],
            'projects' => [['id', 'name', 'no_project']],
        ]);

        $response->assertJsonFragment(['id' => $requester->id]);
        $response->assertJsonFragment(['id' => $orgchart->id]);
        $response->assertJsonFragment(['id' => $project->id_project]);
    }

    public function test_options_endpoint_filters_by_user_id(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create(['org_name' => 'Finance Dept', 'org_code' => 'FIN']);
        $employee = HrdEmployee::factory()->create([
            'employee_id' => $requester->username,
            'orgchart_id' => $orgchart->id,
        ]);

        $projectAssigned = TbProject::factory()->create(['no_project' => 'PRJ-01', 'project_name' => 'Assigned Project']);
        $projectUnassigned = TbProject::factory()->create(['no_project' => 'PRJ-02', 'project_name' => 'Unassigned Project']);

        TbAssignProject::create([
            'npk' => $requester->username,
            'no_project' => 'PRJ-01',
            'id_rbs' => 'P1221',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'DELETION' => '0',
        ]);

        $response = $this->actingAs($admin)->getJson(route('smart.inventory.consumables.request-options', ['user_id' => $requester->id]));
        $response->assertOk();

        // Department should only contain the requester's department
        $departments = $response->json('departments');
        $this->assertCount(1, $departments);
        $this->assertEquals($orgchart->id, $departments[0]['id']);

        // Projects should only contain the assigned project
        $projects = $response->json('projects');
        $this->assertCount(1, $projects);
        $this->assertEquals($projectAssigned->id_project, $projects[0]['id']);
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
        $this->assertNotNull($fulfillment->assigned_at);
        $this->assertNotNull($fulfillment->confirmed_at);
        $this->assertNotNull($fulfillment->completed_at);

        // 5. InventoryLog created with stock_out and negative quantity_change
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $requester->id,
            'action_type' => 'stock_out',
            'quantity_change' => -10,
            'note' => $note,
        ]);

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
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 15, // Exceeds available stock (10)
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

        // Non-consumable barang
        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $nonConsumableBarang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $lot = Lot::factory()->create([
            'barang_id' => $nonConsumableBarang->id,
            'current_quantity' => 10,
        ]);

        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot->id), [
            'user_id' => $requester->id,
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 2,
            'note' => 'Bukan habis pakai',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('quantity');
        $this->assertEquals(10, $lot->fresh()->current_quantity);
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

        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $requester->id,
            'quantity_change' => -25,
        ]);
    }

    public function test_can_create_non_specific_manual_request_fifo_multiple_lots(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();

        // Lot 1: Oldest lot (receipt 20 days ago) with 20 available
        $oldLot = $this->createConsumableLot($barang, 20, now()->subDays(20));

        // Lot 2: Middle lot (receipt 10 days ago) with 30 available
        $midLot = $this->createConsumableLot($barang, 30, now()->subDays(10));

        // Lot 3: Newest lot (receipt 2 days ago) with 50 available
        $newLot = $this->createConsumableLot($barang, 50, now()->subDays(2));

        // Request 35 total:
        // - 20 from oldLot (exhausted -> 0)
        // - 15 from midLot (remaining -> 15)
        // - 0 from newLot (untouched -> 50)
        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
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

        // Multiple fulfillments (2 records)
        $fulfillments = RequestFulfillment::where('request_item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(2, $fulfillments);

        $this->assertEquals($oldLot->id, $fulfillments[0]->lot_id);
        $this->assertEquals(20, $fulfillments[0]->quantity_fulfilled);
        $this->assertNotNull($fulfillments[0]->assigned_at);
        $this->assertNotNull($fulfillments[0]->confirmed_at);
        $this->assertNotNull($fulfillments[0]->completed_at);

        $this->assertEquals($midLot->id, $fulfillments[1]->lot_id);
        $this->assertEquals(15, $fulfillments[1]->quantity_fulfilled);
        $this->assertNotNull($fulfillments[1]->assigned_at);
        $this->assertNotNull($fulfillments[1]->confirmed_at);
        $this->assertNotNull($fulfillments[1]->completed_at);

        // Multiple inventory logs (2 records)
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $oldLot->id,
            'quantity_change' => -20,
            'action_type' => 'stock_out',
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $midLot->id,
            'quantity_change' => -15,
            'action_type' => 'stock_out',
        ]);
    }

    public function test_cannot_create_non_specific_request_when_quantity_exceeds_total_stock(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create();
        $barang = $this->createConsumableBarang();

        $this->createConsumableLot($barang, 15, now()->subDays(10));
        $this->createConsumableLot($barang, 10, now()->subDays(5));
        // Total available = 25

        $response = $this->actingAs($admin)->post(route('smart.inventory.barangs.manual-request', $barang->id), [
            'user_id' => $requester->id,
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 30, // Exceeds 25
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
            'utilization' => 'corporate',
            'org_id' => null, // Missing org_id
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
            'utilization' => 'project',
            'project_id' => null, // Missing project_id
            'quantity' => 5,
            'note' => 'Tanpa project',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('project_id');
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
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 5,
            'note' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 1. Lot quantity deducted
        $this->assertEquals(25, $lot->fresh()->current_quantity);

        // 2. SmartRequest created with reasoning = null
        $smartReq = SmartRequest::where('user_id', $requester->id)->latest('id')->first();
        $this->assertNotNull($smartReq);
        $this->assertNull($smartReq->reasoning);

        // 3. InventoryLog created with note = null
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $requester->id,
            'action_type' => 'stock_out',
            'quantity_change' => -5,
            'note' => null,
        ]);
    }
}

