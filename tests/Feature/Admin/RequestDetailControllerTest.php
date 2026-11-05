<?php

namespace Tests\Feature\Admin;

use App\Models\HrdOrgchart;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestDetailControllerTest extends TestCase
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
            'employee_name' => 'Alice Borrower',
        ]);
    }

    public function test_show_endpoint_returns_complete_request_data_and_inertia_component(): void
    {
        $category = Category::factory()->create(['code' => 'CATB', 'name' => 'Category Borrow']);
        $assetType = Type::factory()->create([
            'category_id' => $category->id,
            'code' => 'ASTB',
            'is_consumable' => false,
        ]);

        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $assetModel = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Model Laptop',
        ]);

        $lot = Lot::factory()->create(['model_id' => $assetModel->id]);

        $stAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'number' => 'AST-BRW-01',
            'status' => 'Dipinjam (ST)',
            'borrowing_type' => 'ST',
        ]);

        $department = HrdOrgchart::create([
            'org_name' => 'IT Department',
            'org_code' => 'IT-DEPT',
            'employee_id' => 1,
        ]);

        $reqSt = SmartRequest::create([
            'request_number' => 'BRW-ST-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'org_id' => $department->id,
            'utilization' => 'corporate',
            'status' => 'borrow',
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(5),
            'form_url' => 'request_forms/sample.pdf',
        ]);

        $itemSt = RequestItem::create([
            'request_id' => $reqSt->id,
            'model_id' => $assetModel->id,
            'type_id' => $assetType->id,
            'quantity_requested' => 1,
            'status' => 'fulfilled',
        ]);

        RequestFulfillment::create([
            'request_item_id' => $itemSt->id,
            'asset_id' => $stAsset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now()->subDays(2),
        ]);

        $showResponse = $this->actingAs($this->admin)->get(route('smart.requests.show', $reqSt->id));
        $showResponse->assertOk();
        $showRequest = $showResponse->viewData('page')['props']['request'] ?? [];
        $this->assertEquals('BRW-ST-01', $showRequest['number']);
        $this->assertEquals('IT Department', $showRequest['pemanfaatanDetail']);
        $this->assertEquals('st', $showRequest['specific_type']);
        $this->assertEquals('borrow', $showRequest['raw_status']);
        $this->assertCount(1, $showRequest['items']);
        $this->assertEquals([$stAsset->number], $showRequest['items'][0]['assets']);
        $showResponse->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Smart/Admin/Requests/RequestDetail')
            ->has('locations')
            ->has('request')
        );
    }

    public function test_show_endpoint_resolves_comp_category_specification_from_asset(): void
    {
        $compCat = Category::factory()->create(['code' => 'COMP', 'name' => 'Computer']);
        $compType = Type::factory()->create([
            'category_id' => $compCat->id,
            'code' => 'LTP',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);
        $brand = Brand::factory()->create(['name' => 'ThinkPad']);
        $uom = Uom::factory()->create(['name' => 'Unit']);

        $compModel = InventoryModel::factory()->create([
            'type_id' => $compType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad X1 Carbon',
            'specification' => 'Intel Core Ultra 7',
        ]);

        $lot = Lot::factory()->create(['model_id' => $compModel->id]);

        $customSpec = 'Intel Core Ultra 7 155H, 32GB LPDDR5x, 1TB NVMe, OLED 2.8K';
        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'number' => 'AST-COMP-01',
            'status' => 'Dipinjam (ST)',
            'borrowing_type' => 'ST',
            'comp_specification' => $customSpec,
        ]);

        $req = SmartRequest::create([
            'request_number' => 'BRW-COMP-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(3),
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'model_id' => $compModel->id,
            'type_id' => $compType->id,
            'quantity_requested' => 1,
            'status' => 'fulfilled',
        ]);

        RequestFulfillment::create([
            'request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.requests.show', $req->id));
        $response->assertOk();

        $items = $response->viewData('page')['props']['request']['items'] ?? [];
        $this->assertCount(1, $items);
        $this->assertEquals($customSpec, $items[0]['spec']);
    }

    public function test_show_endpoint_supports_archived_completed_requests(): void
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

        $department = HrdOrgchart::create([
            'org_name' => 'Engineering Department',
            'org_code' => 'ENG-DEPT',
            'employee_id' => 1,
        ]);

        $req = SmartRequest::create([
            'request_number' => 'ARC-0001',
            'user_id' => $this->borrower->id,
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

        $handover = \App\Models\Request\RequestHandover::create([
            'request_id' => $req->id,
            'method' => 'pickup',
            'scheduled_date' => now(),
            'location' => 'HQ',
            'proof_url' => 'handovers/archive_proof.pdf',
            'user_confirmed_at' => now(),
            'auto_confirmed' => true,
        ]);

        RequestFulfillment::create([
            'request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'handover_id' => $handover->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
        ]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => 'return',
            'status_to' => 'success',
            'changed_by' => $this->admin->id,
            'note' => 'Pengembalian aset selesai.',
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.requests.show', $req->id));
        $response->assertOk();

        $pageProps = $response->viewData('page')['props'] ?? [];
        $requestData = $pageProps['request'] ?? [];

        $this->assertEquals('ARC-0001', $requestData['number']);
        $this->assertEquals('success', $requestData['status']);
        $this->assertEquals('success', $requestData['raw_status']);
        $this->assertEquals('st', $requestData['specific_type']);
        $this->assertEquals('Engineering Department', $requestData['requester_department']);
        $this->assertTrue($requestData['is_manual']);
        $this->assertEquals('HQ', $requestData['handover_location']);
        $this->assertStringContainsString('archive_sample.pdf', $requestData['form_url']);
    }

    public function test_finish_all_manual_borrowing_completes_all_fulfillments_and_moves_to_success(): void
    {
        Storage::fake('local');
        $location = Location::factory()->create(['name' => 'Main Warehouse']);
        $cat = Category::factory()->create(['code' => 'CAT1']);
        $type = Type::factory()->create(['category_id' => $cat->id, 'is_consumable' => false]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $model = InventoryModel::factory()->create(['type_id' => $type->id, 'brand_id' => $brand->id, 'uom_id' => $uom->id]);
        $lot = Lot::factory()->create(['model_id' => $model->id]);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);
        $asset2 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'MAN-ALL-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $item = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'type_id' => $type->id, 'quantity_requested' => 2]);
        $ful1 = RequestFulfillment::create(['request_item_id' => $item->id, 'asset_id' => $asset1->id]);
        $ful2 = RequestFulfillment::create(['request_item_id' => $item->id, 'asset_id' => $asset2->id]);

        $proof = UploadedFile::fake()->create('return_proof.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('smart.requests.finishAll', $req->id), [
            'proof' => $proof,
            'location_id' => $location->id,
        ]);

        $response->assertRedirect();
        $req->refresh();
        $this->assertEquals('success', $req->status);

        $asset1->refresh();
        $asset2->refresh();
        $this->assertEquals('Tersedia', $asset1->status);
        $this->assertEquals('Tersedia', $asset2->status);

        $ful1->refresh();
        $ful2->refresh();
        $this->assertNotNull($ful1->return_id);
        $this->assertNotNull($ful2->return_id);

        $return = RequestReturn::where('request_id', $req->id)->first();
        $this->assertNotNull($return);
        $this->assertEquals('Main Warehouse', $return->location);
        Storage::disk('local')->assertExists($return->proof_url);
    }

    public function test_finish_single_asset_completes_target_fulfillment_and_keeps_borrow_status_if_remaining(): void
    {
        Storage::fake('local');
        $location = Location::factory()->create(['name' => 'Storage Unit']);
        $cat = Category::factory()->create(['code' => 'CAT2']);
        $type = Type::factory()->create(['category_id' => $cat->id, 'is_consumable' => false]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $model = InventoryModel::factory()->create(['type_id' => $type->id, 'brand_id' => $brand->id, 'uom_id' => $uom->id]);
        $lot = Lot::factory()->create(['model_id' => $model->id]);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);
        $asset2 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'MAN-ONE-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $item = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'type_id' => $type->id, 'quantity_requested' => 2]);
        $ful1 = RequestFulfillment::create(['request_item_id' => $item->id, 'asset_id' => $asset1->id]);
        $ful2 = RequestFulfillment::create(['request_item_id' => $item->id, 'asset_id' => $asset2->id]);

        $proof = UploadedFile::fake()->create('return_proof.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('smart.requests.finishAsset', [$req->id, $ful1->id]), [
            'proof' => $proof,
            'location_id' => $location->id,
        ]);

        $response->assertRedirect();
        $req->refresh();
        $this->assertEquals('borrow', $req->status);

        $asset1->refresh();
        $asset2->refresh();
        $this->assertEquals('Tersedia', $asset1->status);
        $this->assertEquals('Dipinjam (ST)', $asset2->status);

        $ful1->refresh();
        $ful2->refresh();
        $this->assertNotNull($ful1->return_id);
        $this->assertNull($ful2->return_id);
    }

    public function test_finish_last_remaining_asset_automatically_advances_request_status_to_success(): void
    {
        Storage::fake('local');
        $location = Location::factory()->create(['name' => 'Storage Unit']);
        $cat = Category::factory()->create(['code' => 'CAT3']);
        $type = Type::factory()->create(['category_id' => $cat->id, 'is_consumable' => false]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $model = InventoryModel::factory()->create(['type_id' => $type->id, 'brand_id' => $brand->id, 'uom_id' => $uom->id]);
        $lot = Lot::factory()->create(['model_id' => $model->id]);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'MAN-LST-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $item = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'type_id' => $type->id, 'quantity_requested' => 1]);
        $ful1 = RequestFulfillment::create(['request_item_id' => $item->id, 'asset_id' => $asset1->id]);

        $proof = UploadedFile::fake()->create('return_proof.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('smart.requests.finishAsset', [$req->id, $ful1->id]), [
            'proof' => $proof,
            'location_id' => $location->id,
        ]);

        $response->assertRedirect();
        $req->refresh();
        $this->assertEquals('success', $req->status);
    }

    public function test_finish_endpoints_validate_mandatory_proof_and_location(): void
    {
        $req = SmartRequest::create([
            'request_number' => 'MAN-VAL-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $resAll = $this->actingAs($this->admin)->post(route('smart.requests.finishAll', $req->id), []);
        $resAll->assertSessionHasErrors(['proof', 'location_id']);

        $resAsset = $this->actingAs($this->admin)->post(route('smart.requests.finishAsset', [$req->id, 1]), []);
        $resAsset->assertSessionHasErrors(['proof', 'location_id']);
    }

    public function test_finish_endpoints_reject_non_manual_requests_with_403(): void
    {
        $req = SmartRequest::create([
            'request_number' => 'NAT-BRW-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => null,
        ]);

        $responseAll = $this->actingAs($this->admin)->post(route('smart.requests.finishAll', $req->id));
        $responseAll->assertStatus(403);

        $responseAsset = $this->actingAs($this->admin)->post(route('smart.requests.finishAsset', [$req->id, 999]));
        $responseAsset->assertStatus(403);
    }
}
