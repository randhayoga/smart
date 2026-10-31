<?php

namespace Tests\Feature\Admin;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'active' => 1,
        ]);

        $this->employee = User::factory()->create([
            'active' => 1,
            'employee_name' => 'John Doe',
        ]);
    }

    public function test_archive_index_calculates_specific_type_keys(): void
    {
        $category = Category::factory()->create(['code' => 'CAT1', 'name' => 'Category 1']);
        $consumableType = Type::factory()->create([
            'category_id' => $category->id,
            'code' => 'CON1',
            'is_consumable' => true,
        ]);
        $assetType = Type::factory()->create([
            'category_id' => $category->id,
            'code' => 'AST1',
            'is_consumable' => false,
        ]);

        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $consumableModel = InventoryModel::factory()->create([
            'type_id' => $consumableType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $assetModel = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $assetLot = Lot::factory()->create(['model_id' => $assetModel->id]);

        // 1. Consumable request
        $reqConsumable = SmartRequest::create([
            'request_number' => 'REQ-CON-01',
            'user_id' => $this->employee->id,
            'approver_id' => $this->admin->id,
            'status' => 'success',
        ]);
        RequestItem::create([
            'request_id' => $reqConsumable->id,
            'model_id' => $consumableModel->id,
            'quantity_requested' => 5,
            'status' => 'fulfilled',
        ]);

        // 2. Short-term borrowing
        $reqSt = SmartRequest::create([
            'request_number' => 'REQ-ST-01',
            'user_id' => $this->employee->id,
            'approver_id' => $this->admin->id,
            'status' => 'success',
        ]);
        RequestItem::create([
            'request_id' => $reqSt->id,
            'model_id' => $assetModel->id,
            'quantity_requested' => 1,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'status' => 'fulfilled',
        ]);

        // 3. Rent borrowing
        $reqRent = SmartRequest::create([
            'request_number' => 'REQ-RENT-01',
            'user_id' => $this->employee->id,
            'approver_id' => $this->admin->id,
            'status' => 'success',
        ]);
        RequestItem::create([
            'request_id' => $reqRent->id,
            'model_id' => $assetModel->id,
            'quantity_requested' => 1,
            'start_date' => now(),
            'end_date' => now()->addDays(10),
            'status' => 'fulfilled',
        ]);
        RequestStatusLog::create([
            'request_id' => $reqRent->id,
            'status_from' => 'draft',
            'status_to' => 'borrow',
            'changed_by' => $this->admin->id,
            'note' => 'Peminjaman (sewa) manual dikonfirmasi oleh Admin',
            'created_at' => now(),
        ]);

        // 4. Long-term assignment
        $reqLt = SmartRequest::create([
            'request_number' => 'REQ-LT-01',
            'user_id' => $this->employee->id,
            'approver_id' => $this->admin->id,
            'status' => 'success',
        ]);
        RequestItem::create([
            'request_id' => $reqLt->id,
            'model_id' => $assetModel->id,
            'quantity_requested' => 1,
            'start_date' => now(),
            'end_date' => null,
            'status' => 'fulfilled',
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.archive'));
        $response->assertOk();

        $archiveList = $response->viewData('page')['props']['archiveList'] ?? [];
        $byNumber = collect($archiveList)->keyBy('number');

        $this->assertEquals('consumable', $byNumber['REQ-CON-01']['type']);
        $this->assertEquals('st', $byNumber['REQ-ST-01']['type']);
        $this->assertEquals('rent', $byNumber['REQ-RENT-01']['type']);
        $this->assertEquals('lt', $byNumber['REQ-LT-01']['type']);
        $this->assertEquals('John Doe', $byNumber['REQ-CON-01']['requester']);
    }

    public function test_archive_show_loads_optimized_data_and_attributes(): void
    {
        $category = Category::factory()->create(['code' => 'COMP', 'name' => 'Computer']);
        $assetType = Type::factory()->create([
            'category_id' => $category->id,
            'code' => 'LAPT',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $uom = Uom::factory()->create();

        $assetModel = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Latitude 5420',
            'specification' => 'Intel Core i5',
        ]);

        $lot = Lot::factory()->create(['model_id' => $assetModel->id]);

        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'number' => 'AST-ARC-001',
            'status' => 'Tersedia',
            'borrowing_type' => 'ST',
            'comp_specification' => 'Intel Core i7-1185G7, 16GB RAM, 512GB SSD',
        ]);

        $department = \App\Models\HrdOrgchart::create([
            'org_name' => 'Engineering Department',
            'org_code' => 'ENG-DEPT',
            'employee_id' => 1,
        ]);

        $req = SmartRequest::create([
            'request_number' => 'ARC-0001',
            'user_id' => $this->employee->id,
            'approver_id' => $this->admin->id,
            'org_id' => $department->id,
            'utilization' => 'corporate',
            'status' => 'success',
            'form_url' => 'request_forms/archive_sample.pdf',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'model_id' => $assetModel->id,
            'type_id' => $assetType->id,
            'quantity_requested' => 1,
            'status' => 'fulfilled',
        ]);

        \App\Models\Request\RequestFulfillment::create([
            'request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => now(),
            'completed_at' => now(),
        ]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => 'return',
            'status_to' => 'success',
            'changed_by' => $this->admin->id,
            'note' => 'Pengembalian aset selesai.',
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.archive.show', $req->id));
        $response->assertOk();

        $pageProps = $response->viewData('page')['props'] ?? [];
        $requestData = $pageProps['request'] ?? [];

        $this->assertEquals('ARC-0001', $requestData['number']);
        $this->assertEquals('success', $requestData['status']);
        $this->assertEquals('success', $requestData['raw_status']);
        $this->assertEquals('st', $requestData['specific_type']);
        $this->assertEquals('Engineering Department', $requestData['requester_department']);
        $this->assertTrue($requestData['is_manual']);
        $this->assertStringContainsString('archive_sample.pdf', $requestData['form_url']);

        $this->assertCount(1, $requestData['items']);
        $this->assertEquals('Dell', $requestData['items'][0]['brand']);
        $this->assertEquals('Latitude 5420', $requestData['items'][0]['name']);
        $this->assertEquals('Intel Core i7-1185G7, 16GB RAM, 512GB SSD', $requestData['items'][0]['spec']);
        $this->assertEquals('COMP', $requestData['items'][0]['category_code']);
        $this->assertEquals(['AST-ARC-001'], $requestData['items'][0]['assets']);
    }
}
