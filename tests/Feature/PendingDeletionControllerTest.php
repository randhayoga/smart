<?php

namespace Tests\Feature;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pending Deletion Controller Feature Tests
 *
 * Verifies that the pending deletion admin page supplies complete data without blank fields for datatable rendering.
 */
class PendingDeletionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['employee_id' => '255578']);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $permission = Permission::firstOrCreate(
            ['name' => 'inventory.status_approval.request'],
            ['label' => 'Request Inventory Status Approval', 'group' => 'inventory']
        );
        $adminRole->givePermissionTo($permission);
        $this->admin->assignRole($adminRole);

        $category = Category::factory()->create([
            'code' => 'COMP',
            'name' => 'Computer',
        ]);
        $type = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'COMP-PC',
            'name' => 'Personal Computer',
            'is_consumable' => false,
        ]);
        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $uom = Uom::factory()->create(['name' => 'Unit']);

        $model = InventoryModel::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkCentre M70q',
            'specification' => 'Intel Core i5, 16GB RAM, 512GB SSD',
        ]);

        $organizer = Organizer::factory()->create(['name' => 'IT Department']);
        $vendor = Vendor::factory()->create(['name' => 'PT Mitra Solusi']);
        $location = Location::factory()->create(['name' => 'Main Office Floor 2']);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
        ]);

        $this->asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'AST-PC-001',
            'status' => 'Tersedia,Pending:DM',
            'condition' => 'Rusak Total',
            'comp_specification' => 'Intel Core i5, 16GB RAM, 512GB SSD',
        ]);

        AssetDeletionApproval::create([
            'asset_id' => $this->asset->id,
            'requester_id' => $this->admin->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/memo.pdf',
        ]);
    }

    public function test_pending_deletion_page_returns_complete_fields_without_blanks(): void
    {
        $response = $this->actingAs($this->admin)->get(route('smart.inventory.pending-deletion'));
        $response->assertOk();

        $page = $response->viewData('page');
        $this->assertNotEmpty($page['props']['assets']);
        $unit = $page['props']['assets'][0];

        // Critical table columns in NonConsumableListTab
        $this->assertEquals('AST-PC-001', $unit['number']);
        $this->assertEquals('ThinkCentre M70q', $unit['barang_nama']);
        $this->assertEquals('Lenovo', $unit['barang_brand']);
        $this->assertEquals('Computer', $unit['barang_category']);
        $this->assertEquals('COMP', $unit['barang_category_code']);
        $this->assertEquals('Personal Computer', $unit['barang_subcategory']);
        $this->assertEquals('Intel Core i5, 16GB RAM, 512GB SSD', $unit['comp_specification']);
        $this->assertEquals('Tersedia,Pending:DM', $unit['status']);
        $this->assertEquals('Rusak Total', $unit['condition']);
        $this->assertStringContainsString('Main Office', $unit['location']);

        // Model & Lot parity
        $this->assertEquals('ThinkCentre M70q', $unit['model_name']);
        $this->assertEquals('Lenovo', $unit['model_brand']);
        $this->assertEquals('Computer', $unit['model_category']);
        $this->assertEquals('Personal Computer', $unit['model_type']);
        $this->assertEquals('IT Department', $unit['lot_organizer']);
        $this->assertEquals('PT Mitra Solusi', $unit['lot_vendor']);
    }

    public function test_guest_cannot_access_pending_deletion(): void
    {
        $response = $this->get(route('smart.inventory.pending-deletion'));
        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }
}
