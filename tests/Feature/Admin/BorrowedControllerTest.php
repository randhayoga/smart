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
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }
}
