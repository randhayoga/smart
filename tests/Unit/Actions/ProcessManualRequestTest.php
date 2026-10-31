<?php

namespace Tests\Unit\Actions;

use App\Actions\Request\ProcessManualRequest;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessManualRequestTest extends TestCase
{
    use RefreshDatabase;

    private ProcessManualRequest $action;
    private User $admin;
    private User $borrower;
    private User $dm;
    private HrdOrgchart $orgchart;
    private Category $category;
    private Brand $brand;
    private Uom $uom;
    private Organizer $organizer;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->action = app(ProcessManualRequest::class);

        $this->dm = User::factory()->create([
            'employee_id' => 'EMP-DM01',
            'employee_name' => 'Division Manager',
            'active' => 1,
        ]);

        $this->orgchart = HrdOrgchart::create([
            'org_code' => 'ORG-IT10',
            'org_name' => 'IT Division',
            'employee_id' => $this->dm->id,
        ]);

        $this->admin = User::factory()->create([
            'employee_id' => 'EMP-ADM01',
            'employee_name' => 'Admin User',
            'orgchart_id' => $this->orgchart->id,
            'active' => 1,
        ]);

        $this->borrower = User::factory()->create([
            'employee_id' => 'EMP-USR01',
            'employee_name' => 'Borrower User',
            'orgchart_id' => $this->orgchart->id,
            'active' => 1,
        ]);

        $this->category = Category::factory()->create(['code' => 'CAT1', 'name' => 'IT Equipment']);
        $this->brand = Brand::factory()->create(['name' => 'Logitech']);
        $this->uom = Uom::factory()->create(['name' => 'Pcs']);
        $this->organizer = Organizer::factory()->create(['name' => 'IT']);
        $this->location = Location::factory()->create(['name' => 'Main Warehouse']);
    }

    public function test_executes_consumable_manual_request_with_fifo_deduction_and_logs(): void
    {
        $consumableType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'CNS1',
            'name' => 'Cable',
            'is_consumable' => true,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $consumableType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'LAN Cable Cat6',
            'specification' => '5 Meter',
            'min_stock_threshold' => 2,
        ]);

        $olderLot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'initial_quantity' => 5,
            'current_quantity' => 5,
            'purchase_grant_date' => '2025-01-10',
        ]);

        $newerLot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'purchase_grant_date' => '2025-06-15',
        ]);

        $formFile = UploadedFile::fake()->create('signed-form.pdf', 120, 'application/pdf');

        $smartRequest = $this->action->execute([
            'request_type' => 'consumable',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->dm->id,
            'utilization' => 'corporate',
            'org_id' => $this->orgchart->id,
            'project_id' => null,
            'reasoning' => 'Need cables for server rack',
            'date' => '2026-10-27',
            'items' => [
                [
                    'model_id' => $model->id,
                    'quantity' => 8,
                    'reasoning' => 'Rack A & Rack B patching',
                ],
            ],
        ], $formFile, $this->admin->id);

        $this->assertEquals('success', $smartRequest->status);
        $this->assertNotNull($smartRequest->form_url);
        Storage::disk('local')->assertExists($smartRequest->form_url);

        $olderLot->refresh();
        $newerLot->refresh();
        $this->assertEquals(0, $olderLot->current_quantity);
        $this->assertEquals(7, $newerLot->current_quantity);

        $item = RequestItem::where('request_id', $smartRequest->id)->firstOrFail();
        $this->assertEquals('fulfilled', $item->status);
        $this->assertEquals(8, $item->quantity_requested);
        $this->assertNull($item->start_date);
        $this->assertNull($item->end_date);
        $this->assertEquals('Rack A & Rack B patching', $item->reasoning);

        $fulfillments = RequestFulfillment::where('request_item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(2, $fulfillments);
        $this->assertEquals($olderLot->id, $fulfillments[0]->lot_id);
        $this->assertEquals(5, $fulfillments[0]->quantity_fulfilled);
        $this->assertNotNull($fulfillments[0]->assigned_at);
        $this->assertNotNull($fulfillments[0]->confirmed_at);
        $this->assertNotNull($fulfillments[0]->completed_at);

        $this->assertEquals($newerLot->id, $fulfillments[1]->lot_id);
        $this->assertEquals(3, $fulfillments[1]->quantity_fulfilled);

        $logs = InventoryLog::where('action_type', 'stock_out')->get();
        $this->assertCount(2, $logs);
        $this->assertEquals($this->borrower->id, $logs[0]->user_id);
        $this->assertEquals($this->borrower->id, $logs[1]->user_id);

        $statusLog = RequestStatusLog::where('request_id', $smartRequest->id)->first();
        $this->assertNotNull($statusLog);
        $this->assertEquals($this->admin->id, $statusLog->changed_by);
        $this->assertDatabaseHas('request_admin_confirmations', [
            'request_id' => $smartRequest->id,
            'admin_id' => $this->admin->id,
            'action' => 'confirm',
        ]);
    }



    public function test_executes_short_term_borrowing_and_updates_asset_status_and_lifecycle(): void
    {
        $assetType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'LAP1',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'ThinkPad T14',
        ]);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $asset1 = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-ST-001',
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'ST',
        ]);

        $asset2 = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-ST-002',
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'ST',
        ]);

        $formFile = UploadedFile::fake()->create('borrow-st.pdf', 100, 'application/pdf');

        $smartRequest = $this->action->execute([
            'request_type' => 'st',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->dm->id,
            'utilization' => 'corporate',
            'org_id' => $this->orgchart->id,
            'project_id' => null,
            'reasoning' => 'Field site visit',
            'start_date' => '2026-10-27',
            'end_date' => '2026-11-05',
            'items' => [
                ['asset_id' => $asset1->id, 'reasoning' => 'Primary unit'],
                ['asset_id' => $asset2->id, 'reasoning' => 'Backup unit'],
            ],
        ], $formFile, $this->admin->id);

        $this->assertEquals('borrow', $smartRequest->status);
        $this->assertNotNull($smartRequest->form_url);

        $asset1->refresh();
        $asset2->refresh();
        $this->assertEquals('Dipinjam (ST)', $asset1->status);
        $this->assertEquals('Dipinjam (ST)', $asset2->status);

        $items = RequestItem::where('request_id', $smartRequest->id)->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertEquals('fulfilled', $items[0]->status);
        $this->assertEquals('2026-10-27 00:00:00', $items[0]->start_date->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-11-05 23:59:59', $items[0]->end_date->format('Y-m-d H:i:s'));
        $this->assertEquals('Primary unit', $items[0]->reasoning);

        $fulfillment = RequestFulfillment::where('request_item_id', $items[0]->id)->firstOrFail();
        $this->assertEquals($asset1->id, $fulfillment->asset_id);
        $this->assertEquals('2026-10-27', $fulfillment->assigned_at->format('Y-m-d'));
        $this->assertEquals('2026-10-27', $fulfillment->confirmed_at->format('Y-m-d'));
        $this->assertNull($fulfillment->completed_at);

        $openLifecycle = AssetLifecycle::where('asset_id', $asset1->id)->whereNull('end_date')->first();
        $this->assertNotNull($openLifecycle);
        $this->assertEquals('Peminjaman', $openLifecycle->action_type);
        $this->assertEquals('Dipinjam (ST)', $openLifecycle->status);
        $this->assertEquals($this->borrower->id, $openLifecycle->actor_id);

        $statusLog = RequestStatusLog::where('request_id', $smartRequest->id)->first();
        $this->assertNotNull($statusLog);
        $this->assertEquals($this->admin->id, $statusLog->changed_by);
        $this->assertDatabaseHas('request_admin_confirmations', [
            'request_id' => $smartRequest->id,
            'admin_id' => $this->admin->id,
            'action' => 'confirm',
        ]);
    }



    public function test_executes_long_term_assignment_and_sets_di_assign_lt_status(): void
    {
        $assetType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'MON1',
            'name' => 'Monitor',
            'is_consumable' => false,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Dell 27 Monitor',
        ]);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-LT-001',
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
        ]);

        $formFile = UploadedFile::fake()->create('assign-lt.pdf', 100, 'application/pdf');

        $smartRequest = $this->action->execute([
            'request_type' => 'lt',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->dm->id,
            'utilization' => 'corporate',
            'org_id' => $this->orgchart->id,
            'project_id' => null,
            'reasoning' => 'Workstation setup',
            'start_date' => '2026-10-27',
            'items' => [
                ['asset_id' => $asset->id, 'reasoning' => 'Dedicated desk monitor'],
            ],
        ], $formFile, $this->admin->id);

        $this->assertEquals('borrow', $smartRequest->status);

        $asset->refresh();
        $this->assertEquals('Di-assign (LT)', $asset->status);

        $item = RequestItem::where('request_id', $smartRequest->id)->firstOrFail();
        $this->assertEquals('fulfilled', $item->status);
        $this->assertEquals('2026-10-27', $item->start_date->format('Y-m-d'));
        $this->assertNull($item->end_date);
        $this->assertEquals('Dedicated desk monitor', $item->reasoning);

        $fulfillment = RequestFulfillment::where('request_item_id', $item->id)->firstOrFail();
        $this->assertEquals($asset->id, $fulfillment->asset_id);
        $this->assertEquals('2026-10-27', $fulfillment->assigned_at->format('Y-m-d'));
        $this->assertEquals('2026-10-27', $fulfillment->confirmed_at->format('Y-m-d'));
        $this->assertNull($fulfillment->completed_at);

        $openLifecycle = AssetLifecycle::where('asset_id', $asset->id)->whereNull('end_date')->first();
        $this->assertNotNull($openLifecycle);
        $this->assertEquals('Assignment', $openLifecycle->action_type);
        $this->assertEquals('Di-assign (LT)', $openLifecycle->status);
        $this->assertEquals($this->borrower->id, $openLifecycle->actor_id);

        $statusLog = RequestStatusLog::where('request_id', $smartRequest->id)->first();
        $this->assertNotNull($statusLog);
        $this->assertEquals($this->admin->id, $statusLog->changed_by);
        $this->assertDatabaseHas('request_admin_confirmations', [
            'request_id' => $smartRequest->id,
            'admin_id' => $this->admin->id,
            'action' => 'confirm',
        ]);
    }

    public function test_executes_rent_borrowing_with_st_and_lt_assets_and_sets_dipinjam_sewa_status(): void
    {
        $assetType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'RNT1',
            'name' => 'Project Equipment',
            'is_consumable' => false,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Survey Station',
        ]);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $stAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-RENT-ST',
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'ST',
        ]);

        $ltAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-RENT-LT',
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
        ]);

        $formFile = UploadedFile::fake()->create('rent-form.pdf', 100, 'application/pdf');

        $smartRequest = $this->action->execute([
            'request_type' => 'rent',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->dm->id,
            'utilization' => 'corporate',
            'org_id' => $this->orgchart->id,
            'project_id' => null,
            'reasoning' => 'Vendor rental contract',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'items' => [
                ['asset_id' => $stAsset->id, 'reasoning' => 'Rent unit 1'],
                ['asset_id' => $ltAsset->id, 'reasoning' => 'Rent unit 2'],
            ],
        ], $formFile, $this->admin->id);

        $this->assertEquals('borrow', $smartRequest->status);

        $stAsset->refresh();
        $ltAsset->refresh();
        $this->assertEquals('Dipinjam (sewa)', $stAsset->status);
        $this->assertEquals('Dipinjam (sewa)', $ltAsset->status);

        $items = RequestItem::where('request_id', $smartRequest->id)->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertEquals('fulfilled', $items[0]->status);
        $this->assertEquals('2026-11-01 00:00:00', $items[0]->start_date->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-11-10 23:59:59', $items[0]->end_date->format('Y-m-d H:i:s'));

        $lifecycles = AssetLifecycle::whereIn('asset_id', [$stAsset->id, $ltAsset->id])
            ->whereNull('end_date')
            ->get();
        $this->assertCount(2, $lifecycles);
        foreach ($lifecycles as $lc) {
            $this->assertEquals('Peminjaman', $lc->action_type);
            $this->assertEquals('Dipinjam (sewa)', $lc->status);
            $this->assertEquals($this->borrower->id, $lc->actor_id);
        }

        $statusLog = RequestStatusLog::where('request_id', $smartRequest->id)->first();
        $this->assertNotNull($statusLog);
        $this->assertEquals('Vendor rental contract', $statusLog->note);
    }

    public function test_throws_validation_exception_when_asset_has_pending_approval_or_wrong_borrowing_type(): void
    {
        $assetType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'LAP2',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
        ]);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $pendingAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-PND-001',
            'status' => 'Tersedia',
            'approval_status' => 'Pending:DM',
            'condition' => 'Rusak Total',
            'borrowing_type' => 'ST',
        ]);

        $formFile = UploadedFile::fake()->create('form.pdf', 100, 'application/pdf');

        $this->expectException(ValidationException::class);

        $this->action->execute([
            'request_type' => 'st',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->dm->id,
            'utilization' => 'corporate',
            'org_id' => $this->orgchart->id,
            'project_id' => null,
            'reasoning' => null,
            'start_date' => '2026-10-27',
            'end_date' => '2026-10-30',
            'items' => [
                ['asset_id' => $pendingAsset->id, 'reasoning' => null],
            ],
        ], $formFile, $this->admin->id);
    }
}
