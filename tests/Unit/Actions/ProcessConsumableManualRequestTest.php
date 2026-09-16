<?php

namespace Tests\Unit\Actions;

use App\Actions\Request\ProcessConsumableManualRequest;
use App\Models\AdmUser;
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
use App\Models\TbProject;
use App\Services\InventoryLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessConsumableManualRequestTest extends TestCase
{
    use RefreshDatabase;

    private ProcessConsumableManualRequest $action;
    private AdmUser $admin;
    private AdmUser $requester;
    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ProcessConsumableManualRequest::class);
        $this->admin = AdmUser::factory()->create(['name' => 'Admin User']);
        $this->requester = AdmUser::factory()->create(['name' => 'Requester User']);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $this->barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
    }

    private function createLot(int $quantity): Lot
    {
        $location = Location::firstOrCreate(['name' => 'Gudang Utama'], ['full_name' => 'Gudang Utama']);
        return Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'location_id' => $location->id,
            'initial_quantity' => $quantity,
            'current_quantity' => $quantity,
        ]);
    }

    public function test_execute_creates_request_item_fulfillment_and_deducts_lot_for_corporate(): void
    {
        $orgchart = HrdOrgchart::factory()->create(['org_name' => 'IT Department']);
        $lot = $this->createLot(50);

        $smartReq = $this->action->execute(
            admin: $this->admin,
            requester: $this->requester,
            barang: $this->barang,
            allocations: [
                ['lot' => $lot, 'quantity' => 15],
            ],
            totalQty: 15,
            utilization: 'corporate',
            orgId: $orgchart->id,
            projectId: null,
            note: 'Pengadaan printer ink',
            requestDate: '2026-09-15'
        );

        $this->assertInstanceOf(SmartRequest::class, $smartReq);
        $this->assertEquals('success', $smartReq->status);
        $this->assertEquals($this->requester->id, $smartReq->user_id);
        $this->assertEquals($this->admin->id, $smartReq->approver_id);
        $this->assertEquals('corporate', $smartReq->utilization);
        $this->assertEquals($orgchart->id, $smartReq->org_id);
        $this->assertEquals('Pengadaan printer ink', $smartReq->reasoning);

        // Lot deducted
        $this->assertEquals(35, $lot->fresh()->current_quantity);

        // RequestItem
        $item = RequestItem::where('request_id', $smartReq->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(15, $item->quantity_requested);
        $this->assertEquals('fulfilled', $item->status);
        $this->assertEquals($this->barang->id, $item->barang_id);
        $this->assertEquals('2026-09-15', $item->start_date->toDateString());

        // RequestFulfillment
        $fulfillment = RequestFulfillment::where('request_item_id', $item->id)->first();
        $this->assertNotNull($fulfillment);
        $this->assertEquals($lot->id, $fulfillment->lot_id);
        $this->assertEquals(15, $fulfillment->quantity_fulfilled);
        $this->assertEquals('2026-09-15', $fulfillment->assigned_at->toDateString());
        $this->assertEquals('2026-09-15', $fulfillment->confirmed_at->toDateString());
        $this->assertEquals('2026-09-15', $fulfillment->completed_at->toDateString());

        $log = InventoryLog::where('lot_id', $lot->id)->latest('id')->first();
        $this->assertEquals('2026-09-15', $log->created_at->toDateString());

        // InventoryLog
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $this->barang->id,
            'lot_id' => $lot->id,
            'user_id' => $this->requester->id,
            'action_type' => 'stock_out',
            'quantity_change' => -15,
            'note' => "Permintaan untuk Corporate {$orgchart->org_name}, dengan catatan \"Pengadaan printer ink\"",
        ]);

        // RequestStatusLog
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $smartReq->id,
            'status_from' => 'draft',
            'status_to' => 'success',
            'changed_by' => $this->admin->id,
        ]);
    }

    public function test_execute_handles_multi_lot_allocations(): void
    {
        $project = TbProject::factory()->create(['project_name' => 'MRT Jakarta']);
        $lot1 = $this->createLot(20);
        $lot2 = $this->createLot(30);

        $smartReq = $this->action->execute(
            admin: $this->admin,
            requester: $this->requester,
            barang: $this->barang,
            allocations: [
                ['lot' => $lot1, 'quantity' => 20],
                ['lot' => $lot2, 'quantity' => 10],
            ],
            totalQty: 30,
            utilization: 'project',
            orgId: null,
            projectId: $project->id_project,
            note: 'Proyek MRT'
        );

        $this->assertEquals(0, $lot1->fresh()->current_quantity);
        $this->assertEquals(20, $lot2->fresh()->current_quantity);

        $item = RequestItem::where('request_id', $smartReq->id)->first();
        $fulfillments = RequestFulfillment::where('request_item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(2, $fulfillments);
        $this->assertEquals($lot1->id, $fulfillments[0]->lot_id);
        $this->assertEquals(20, $fulfillments[0]->quantity_fulfilled);
        $this->assertEquals($lot2->id, $fulfillments[1]->lot_id);
        $this->assertEquals(10, $fulfillments[1]->quantity_fulfilled);

        $this->assertDatabaseHas('inventory_logs', [
            'lot_id' => $lot1->id,
            'quantity_change' => -20,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'lot_id' => $lot2->id,
            'quantity_change' => -10,
        ]);
    }

    public function test_execute_evicts_unconfirmed_competing_fulfillments_when_stock_exhausted(): void
    {
        $orgchart = HrdOrgchart::factory()->create(['org_name' => 'HR']);
        $lot = $this->createLot(10);

        // Competing request item and unconfirmed fulfillment
        $competingRequest = SmartRequest::create([
            'request_number' => '092026-0001',
            'user_id' => $this->requester->id,
            'approver_id' => $this->admin->id,
            'utilization' => 'corporate',
            'status' => 'pending',
        ]);
        $competingItem = RequestItem::create([
            'request_id' => $competingRequest->id,
            'subcategory_id' => $this->barang->subcategory_id,
            'barang_id' => $this->barang->id,
            'quantity_requested' => 10,
            'status' => 'approved',
        ]);
        $competingFulfillment = RequestFulfillment::create([
            'request_item_id' => $competingItem->id,
            'lot_id' => $lot->id,
            'unit_id' => null,
            'quantity_fulfilled' => 10,
            'assigned_at' => null,
        ]);

        $this->action->execute(
            admin: $this->admin,
            requester: $this->requester,
            barang: $this->barang,
            allocations: [
                ['lot' => $lot, 'quantity' => 10],
            ],
            totalQty: 10,
            utilization: 'corporate',
            orgId: $orgchart->id,
            projectId: null,
            note: null
        );

        // Lot now 0
        $this->assertEquals(0, $lot->fresh()->current_quantity);
        // Competing fulfillment should be deleted because newQty <= 0
        $this->assertDatabaseMissing('request_fulfillments', ['id' => $competingFulfillment->id]);
    }
}
