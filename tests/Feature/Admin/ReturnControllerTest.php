<?php

namespace Tests\Feature\Admin;

use App\Models\HrdOrgchart;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $borrower;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'active' => 1,
        ]);

        $this->borrower = User::factory()->create([
            'active' => 1,
            'employee_name' => 'Charlie Borrower',
        ]);
    }

    public function test_returns_show_loads_optimized_data_and_attributes(): void
    {
        $category = Category::factory()->create(['code' => 'COMP', 'name' => 'Computer']);
        $assetType = Type::factory()->create([
            'category_id' => $category->id,
            'code' => 'LAPT',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);

        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $uom = Uom::factory()->create();

        $assetModel = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad T14',
            'specification' => 'Intel Core i5',
        ]);

        $lot = Lot::factory()->create(['model_id' => $assetModel->id]);

        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'number' => 'AST-LAPT-001',
            'status' => 'Dipinjam (ST)',
            'borrowing_type' => 'ST',
            'comp_specification' => 'Intel Core i7, 32GB RAM, 1TB SSD',
        ]);

        $department = HrdOrgchart::create([
            'org_name' => 'Finance Department',
            'org_code' => 'FIN-DEPT',
            'employee_id' => 1,
        ]);

        $req = SmartRequest::create([
            'request_number' => 'RET-0001',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'org_id' => $department->id,
            'utilization' => 'corporate',
            'status' => 'return',
            'form_url' => 'request_forms/sample_form.pdf',
        ]);

        RequestReturn::create([
            'request_id' => $req->id,
            'scheduled_date' => now()->addDays(1),
            'location' => 'Gudang Utama Lantai 2',
            'method' => 'self',
            'proof_url' => 'returns/sample_proof.jpg',
            'is_auto_set' => false,
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'model_id' => $assetModel->id,
            'type_id' => $assetType->id,
            'quantity_requested' => 1,
            'status' => 'fulfilled',
        ]);

        RequestFulfillment::create([
            'request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => now(),
        ]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => 'borrow',
            'status_to' => 'return',
            'changed_by' => $this->borrower->id,
            'note' => 'Permintaan pengembalian aset diajukan.',
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.returns.show', $req->id));
        $response->assertOk();

        $pageProps = $response->viewData('page')['props'] ?? [];
        $requestData = $pageProps['request'] ?? [];

        $this->assertEquals('RET-0001', $requestData['number']);
        $this->assertEquals('return', $requestData['status']);
        $this->assertEquals('return', $requestData['raw_status']);
        $this->assertEquals('st', $requestData['specific_type']);
        $this->assertEquals('Finance Department', $requestData['requester_department']);
        $this->assertEquals('Gudang Utama Lantai 2', $requestData['location']);
        $this->assertEquals('Kembalikan sendiri', $requestData['method']);
        $this->assertTrue($requestData['is_manual']);
        $this->assertStringContainsString('sample_form.pdf', $requestData['form_url']);

        $this->assertCount(1, $requestData['items']);
        $this->assertEquals('Lenovo', $requestData['items'][0]['brand']);
        $this->assertEquals('ThinkPad T14', $requestData['items'][0]['name']);
        $this->assertEquals('Intel Core i7, 32GB RAM, 1TB SSD', $requestData['items'][0]['spec']);
        $this->assertEquals('COMP', $requestData['items'][0]['category_code']);
        $this->assertEquals(['AST-LAPT-001'], $requestData['items'][0]['assets']);
    }

    public function test_returns_confirm_batch_updates_assets_and_fulfillments_in_transaction(): void
    {
        $category = Category::factory()->create(['code' => 'COMP']);
        $assetType = Type::factory()->create(['category_id' => $category->id]);
        $assetModel = InventoryModel::factory()->create(['type_id' => $assetType->id]);
        $lot = Lot::factory()->create(['model_id' => $assetModel->id]);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);
        $asset2 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'RET-0002',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'return',
        ]);

        $ret = RequestReturn::create([
            'request_id' => $req->id,
            'scheduled_date' => now(),
            'location' => 'IT Room',
            'method' => 'self',
            'proof_url' => 'returns/sample_proof_2.jpg',
            'is_auto_set' => false,
        ]);

        $item1 = RequestItem::create(['request_id' => $req->id, 'model_id' => $assetModel->id, 'quantity_requested' => 1]);
        $item2 = RequestItem::create(['request_id' => $req->id, 'model_id' => $assetModel->id, 'quantity_requested' => 1]);

        $ful1 = RequestFulfillment::create(['request_item_id' => $item1->id, 'asset_id' => $asset1->id, 'quantity_fulfilled' => 1]);
        $ful2 = RequestFulfillment::create(['request_item_id' => $item2->id, 'asset_id' => $asset2->id, 'quantity_fulfilled' => 1]);

        $response = $this->actingAs($this->admin)->post(route('smart.returns.confirm', $req->id));
        $response->assertSessionHasNoErrors();

        // Verify request status
        $this->assertEquals('success', $req->fresh()->status);

        // Verify batch updated assets
        $this->assertEquals('Tersedia', $asset1->fresh()->status);
        $this->assertEquals('Tersedia', $asset2->fresh()->status);

        // Verify fulfillment assignments completed
        $this->assertNotNull($ful1->fresh()->completed_at);
        $this->assertNotNull($ful2->fresh()->completed_at);

        // Verify request return completed
        $this->assertNotNull($ret->fresh()->completed_at);

        // Verify status log created
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'return',
            'status_to' => 'success',
            'changed_by' => $this->admin->id,
        ]);
    }
}
