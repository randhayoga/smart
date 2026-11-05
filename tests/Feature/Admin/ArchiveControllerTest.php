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

        $redirectResponse = $this->actingAs($this->admin)->get(route('smart.archive'));
        $redirectResponse->assertRedirect(route('smart.requests.index', ['tab' => 'Arsip']));

        $response = $this->actingAs($this->admin)->get(route('smart.requests.index', ['tab' => 'Arsip']));
        $response->assertOk();

        $archiveList = $response->viewData('page')['props']['archiveList'] ?? [];
        $byNumber = collect($archiveList)->keyBy('number');

        $this->assertEquals('consumable', $byNumber['REQ-CON-01']['type']);
        $this->assertEquals('st', $byNumber['REQ-ST-01']['type']);
        $this->assertEquals('rent', $byNumber['REQ-RENT-01']['type']);
        $this->assertEquals('lt', $byNumber['REQ-LT-01']['type']);
        $this->assertEquals('John Doe', $byNumber['REQ-CON-01']['requester']);
    }
}
