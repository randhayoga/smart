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

class BorrowedControllerTest extends TestCase
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

    public function test_borrowed_index_calculates_types_and_fields(): void
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
        ]);

        $assetLot = Lot::factory()->create(['model_id' => $assetModel->id]);

        $stAsset = Asset::factory()->create([
            'lot_id' => $assetLot->id,
            'borrowing_type' => 'ST',
            'status' => 'Dipinjam (ST)',
        ]);

        $rentAsset = Asset::factory()->create([
            'lot_id' => $assetLot->id,
            'borrowing_type' => 'LT',
            'status' => 'Dipinjam (sewa)',
        ]);

        $department = HrdOrgchart::create([
            'org_name' => 'IT Department',
            'org_code' => 'IT-DEPT',
            'employee_id' => 1,
        ]);

        // 1. ST borrow
        $reqSt = SmartRequest::create([
            'request_number' => 'BRW-ST-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'org_id' => $department->id,
            'utilization' => 'corporate',
            'status' => 'borrow',
        ]);
        $itemSt = RequestItem::create([
            'request_id' => $reqSt->id,
            'model_id' => $assetModel->id,
            'quantity_requested' => 1,
            'start_date' => now(),
            'end_date' => now()->addDays(3),
            'status' => 'fulfilled',
        ]);
        RequestFulfillment::create([
            'request_item_id' => $itemSt->id,
            'asset_id' => $stAsset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => now(),
        ]);

        // 2. Rent borrow
        $reqRent = SmartRequest::create([
            'request_number' => 'BRW-RENT-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
        ]);
        $itemRent = RequestItem::create([
            'request_id' => $reqRent->id,
            'model_id' => $assetModel->id,
            'quantity_requested' => 1,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'status' => 'fulfilled',
        ]);
        RequestFulfillment::create([
            'request_item_id' => $itemRent->id,
            'asset_id' => $rentAsset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => now(),
        ]);
        RequestStatusLog::create([
            'request_id' => $reqRent->id,
            'status_from' => 'draft',
            'status_to' => 'borrow',
            'changed_by' => $this->admin->id,
            'note' => 'Peminjaman (sewa) manual dikonfirmasi oleh Admin',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.borrowed'));
        $response->assertOk();

        $borrowedList = $response->viewData('page')['props']['borrowedList'] ?? [];
        $byNumber = collect($borrowedList)->keyBy('number');

        $this->assertEquals('st', $byNumber['BRW-ST-01']['type']);
        $this->assertEquals('rent', $byNumber['BRW-RENT-01']['type']);
        $this->assertEquals('Alice Borrower', $byNumber['BRW-ST-01']['borrower']);

        // Test show endpoint
        $showResponse = $this->actingAs($this->admin)->get(route('smart.borrowed.show', $reqSt->id));
        $showResponse->assertOk();
        $showRequest = $showResponse->viewData('page')['props']['request'] ?? [];
        $this->assertEquals('BRW-ST-01', $showRequest['number']);
        $this->assertEquals('IT Department', $showRequest['pemanfaatanDetail']);
        $this->assertEquals('st', $showRequest['specific_type']);
        $this->assertEquals('borrow', $showRequest['raw_status']);
        $this->assertCount(1, $showRequest['items']);
        $this->assertEquals([$stAsset->number], $showRequest['items'][0]['assets']);
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

        // Model specification is intentionally generic or empty for COMP category
        $compModel = InventoryModel::factory()->create([
            'type_id' => $compType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'T14 Gen 3',
            'specification' => 'Generic ThinkPad',
        ]);

        $lot = Lot::factory()->create(['model_id' => $compModel->id]);
        $compAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'number' => 'AST-COMP-01',
            'status' => 'Dipinjam (ST)',
            'borrowing_type' => 'ST',
            'comp_specification' => 'Core i7-1270P, 32GB RAM, 1TB NVMe',
        ]);

        $req = SmartRequest::create([
            'request_number' => 'BRW-COMP-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'utilization' => 'corporate',
            'status' => 'borrow',
            'form_url' => 'request_forms/test_form.pdf',
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
            'asset_id' => $compAsset->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.borrowed.show', $req->id));
        $response->assertOk();

        $requestData = $response->viewData('page')['props']['request'] ?? [];
        $this->assertTrue($requestData['is_manual']);
        $this->assertStringContainsString('test_form.pdf', $requestData['form_url']);
        $this->assertEquals('Core i7-1270P, 32GB RAM, 1TB NVMe', $requestData['items'][0]['spec']);
        $this->assertEquals('COMP', $requestData['items'][0]['category_code']);
    }

    public function test_finish_all_manual_borrowing_completes_all_fulfillments_and_moves_to_archive(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $type = Type::factory()->create(['category_id' => $category->id]);
        $model = InventoryModel::factory()->create(['type_id' => $type->id]);
        $lot = Lot::factory()->create(['model_id' => $model->id]);
        $location = Location::factory()->create(['name' => 'Ruang IFS']);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'borrowing_type' => 'ST', 'status' => 'Dipinjam (ST)']);
        $asset2 = Asset::factory()->create(['lot_id' => $lot->id, 'borrowing_type' => 'ST', 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'MAN-ALL-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $item1 = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'quantity_requested' => 1]);
        $item2 = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'quantity_requested' => 1]);

        $ful1 = RequestFulfillment::create(['request_item_id' => $item1->id, 'asset_id' => $asset1->id, 'quantity_fulfilled' => 1]);
        $ful2 = RequestFulfillment::create(['request_item_id' => $item2->id, 'asset_id' => $asset2->id, 'quantity_fulfilled' => 1]);

        $proof = UploadedFile::fake()->create('proof.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAll', $req->id), [
            'proof' => $proof,
            'location_id' => $location->id,
        ]);
        $response->assertSessionHasNoErrors();

        $this->assertEquals('success', $req->fresh()->status);
        $this->assertEquals('Tersedia', $asset1->fresh()->status);
        $this->assertEquals($location->id, $asset1->fresh()->location_id);
        $this->assertEquals('Tersedia', $asset2->fresh()->status);
        $this->assertEquals($location->id, $asset2->fresh()->location_id);

        $return = RequestReturn::where('request_id', $req->id)->latest('id')->first();
        $this->assertNotNull($return);
        $this->assertEquals('manual', $return->method);
        $this->assertNull($return->scheduled_date);
        $this->assertNotNull($return->completed_at);
        $this->assertNotNull($return->proof_url);
        $this->assertEquals('Ruang IFS', $return->location);

        $this->assertEquals($return->id, $ful1->fresh()->return_id);
        $this->assertEquals($return->id, $ful2->fresh()->return_id);

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'borrow',
            'status_to' => 'success',
            'changed_by' => $this->admin->id,
            'note' => "Seluruh peminjaman diselesaikan secara manual oleh Admin {$this->admin->name} di Ruang IFS.",
        ]);
    }

    public function test_finish_single_asset_completes_target_fulfillment_and_keeps_borrow_status_when_others_remain(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $type = Type::factory()->create(['category_id' => $category->id]);
        $model = InventoryModel::factory()->create(['type_id' => $type->id]);
        $lot = Lot::factory()->create(['model_id' => $model->id]);
        $location = Location::factory()->create(['name' => 'Ruang Server']);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'borrowing_type' => 'ST', 'number' => 'AST-FIN-1', 'status' => 'Dipinjam (ST)']);
        $asset2 = Asset::factory()->create(['lot_id' => $lot->id, 'borrowing_type' => 'ST', 'number' => 'AST-FIN-2', 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'MAN-ONE-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $item1 = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'quantity_requested' => 1]);
        $item2 = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'quantity_requested' => 1]);

        $ful1 = RequestFulfillment::create(['request_item_id' => $item1->id, 'asset_id' => $asset1->id, 'quantity_fulfilled' => 1]);
        $ful2 = RequestFulfillment::create(['request_item_id' => $item2->id, 'asset_id' => $asset2->id, 'quantity_fulfilled' => 1]);

        $proof = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAsset', [$req->id, $ful1->id]), [
            'proof' => $proof,
            'location_id' => $location->id,
        ]);
        $response->assertSessionHasNoErrors();

        // Fulfillment 1 is finished, Asset 1 is Tersedia at new location
        $return1 = RequestReturn::where('request_id', $req->id)->latest('id')->first();
        $this->assertNotNull($return1);
        $this->assertEquals($return1->id, $ful1->fresh()->return_id);
        $this->assertEquals('Tersedia', $asset1->fresh()->status);
        $this->assertEquals($location->id, $asset1->fresh()->location_id);

        // Fulfillment 2 is still active, Asset 2 is still borrowed
        $this->assertNull($ful2->fresh()->return_id);
        $this->assertEquals('Dipinjam (ST)', $asset2->fresh()->status);

        // Request remains in 'borrow' status since asset 2 is still out
        $this->assertEquals('borrow', $req->fresh()->status);

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'borrow',
            'status_to' => 'borrow',
            'changed_by' => $this->admin->id,
            'note' => "Peminjaman aset AST-FIN-1 diselesaikan oleh Admin {$this->admin->name} di Ruang Server.",
        ]);
    }

    public function test_finish_last_remaining_asset_automatically_advances_request_status_to_success(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $type = Type::factory()->create(['category_id' => $category->id]);
        $model = InventoryModel::factory()->create(['type_id' => $type->id]);
        $lot = Lot::factory()->create(['model_id' => $model->id]);
        $location = Location::factory()->create(['name' => 'Ruang IT']);

        $asset1 = Asset::factory()->create(['lot_id' => $lot->id, 'borrowing_type' => 'ST', 'number' => 'AST-FIN-3', 'status' => 'Dipinjam (ST)']);

        $req = SmartRequest::create([
            'request_number' => 'MAN-LAST-1',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => 'request_forms/manual.pdf',
        ]);

        $item1 = RequestItem::create(['request_id' => $req->id, 'model_id' => $model->id, 'quantity_requested' => 1]);
        $ful1 = RequestFulfillment::create(['request_item_id' => $item1->id, 'asset_id' => $asset1->id, 'quantity_fulfilled' => 1]);

        $proof = UploadedFile::fake()->image('proof.webp');

        $response = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAsset', [$req->id, $ful1->id]), [
            'proof' => $proof,
            'location_id' => $location->id,
        ]);
        $response->assertSessionHasNoErrors();

        $return = RequestReturn::where('request_id', $req->id)->latest('id')->first();
        $this->assertNotNull($return);
        $this->assertEquals($return->id, $ful1->fresh()->return_id);
        $this->assertEquals('Tersedia', $asset1->fresh()->status);
        $this->assertEquals($location->id, $asset1->fresh()->location_id);

        // Entire request advances to 'success' since all items are finished
        $this->assertEquals('success', $req->fresh()->status);

        // 1. Asset finish log
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'borrow',
            'status_to' => 'borrow',
            'changed_by' => $this->admin->id,
            'note' => "Peminjaman aset AST-FIN-3 diselesaikan oleh Admin {$this->admin->name} di Ruang IT.",
        ]);

        // 2. Full borrowing finish log
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'borrow',
            'status_to' => 'success',
            'changed_by' => $this->admin->id,
            'note' => "Seluruh peminjaman diselesaikan secara manual oleh Admin {$this->admin->name} di Ruang IT.",
        ]);
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

        // finishAll without payload
        $resAll = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAll', $req->id), []);
        $resAll->assertSessionHasErrors(['proof', 'location_id']);

        // finishAsset without payload
        $resAsset = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAsset', [$req->id, 1]), []);
        $resAsset->assertSessionHasErrors(['proof', 'location_id']);
    }

    public function test_finish_endpoints_reject_non_manual_requests_with_403(): void
    {
        $req = SmartRequest::create([
            'request_number' => 'NAT-BRW-01',
            'user_id' => $this->borrower->id,
            'approver_id' => $this->admin->id,
            'status' => 'borrow',
            'form_url' => null, // Natural request
        ]);

        $responseAll = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAll', $req->id));
        $responseAll->assertForbidden();

        $responseAsset = $this->actingAs($this->admin)->post(route('smart.borrowed.finishAsset', [$req->id, 999]));
        $responseAsset->assertForbidden();
    }
}
