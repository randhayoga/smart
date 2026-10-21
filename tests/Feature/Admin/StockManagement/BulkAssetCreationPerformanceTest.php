<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Models\Auth\Role;
use App\Notifications\AppNotification;
use App\Mail\DMBulkAssetActivationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BulkAssetCreationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected Lot $lot;
    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $permissions = ['inventory.view', 'inventory.manage'];
        foreach ($permissions as $permName) {
            $perm = \App\Models\Auth\Permission::firstOrCreate(['name' => $permName], ['label' => $permName, 'group' => 'inventory']);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $this->admin->assignRole($adminRole);

        $this->manager = User::factory()->create(['email' => 'ifs.manager@test.com']);
        $managerRole = Role::firstOrCreate(['name' => 'ifs_manager'], ['label' => 'IFS Manager']);
        $this->manager->assignRole($managerRole);

        $category = Category::factory()->create(['name' => 'Komputer', 'code' => 'COMP']);
        $type = Type::factory()->create([
            'category_id' => $category->id,
            'name' => 'Laptop',
            'code' => 'LPT',
            'is_consumable' => false,
        ]);
        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $model = InventoryModel::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad T14',
            'number' => 'LPT-0001',
        ]);

        $organizer = Organizer::firstOrCreate(['name' => 'HO']);
        $vendor = Vendor::factory()->create(['name' => 'PT Vendor']);
        $this->location = Location::factory()->create(['name' => 'Gudang Utama']);

        $this->lot = Lot::create([
            'number' => 'LOT-0001-26-LPT-0001',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'initial_quantity' => 100,
            'current_quantity' => 100,
            'po_number' => 'PO-100',
            'purchase_grant_date' => now(),
            'burden' => 'Corporate',
        ]);
    }

    public function test_can_create_50_assets_rapidly_with_consolidated_batch_notification(): void
    {
        Mail::fake();

        $startTime = microtime(true);

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/assets/bulk', [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'price' => 15000000,
            'bulk_quantity' => 50,
        ]);

        $executionDuration = microtime(true) - $startTime;

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertLessThan(5.0, $executionDuration, '50 assets creation should execute well under 5 seconds.');

        $this->assertEquals(50, Asset::where('lot_id', $this->lot->id)->count());
        $this->assertEquals(50, AssetActivationApproval::count());

        // Verify consolidated single batch email sent instead of 50 individual emails
        Mail::assertSent(DMBulkAssetActivationRequest::class, 1);
    }

    public function test_can_bulk_update_assets_atomically_in_batch(): void
    {
        $assets = [];
        for ($i = 1; $i <= 20; $i++) {
            $assets[] = Asset::create([
                'number' => sprintf('000%02d-LPT-HO-PTRE-26', $i),
                'lot_id' => $this->lot->id,
                'location_id' => $this->location->id,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'borrowing_type' => 'LT',
                'classification' => 'Aset',
                'price' => 10000000,
                'burden' => 'Corporate',
            ]);
        }

        $newLocation = Location::factory()->create(['name' => 'Kantor Cabang']);

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => collect($assets)->pluck('id')->toArray(),
            'location_id' => $newLocation->id,
            'borrowing_type' => 'ST',
            'classification' => 'Inventaris',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $updatedAssets = Asset::whereIn('id', collect($assets)->pluck('id'))->get();
        $this->assertCount(20, $updatedAssets);
        foreach ($updatedAssets as $asset) {
            $this->assertEquals($newLocation->id, $asset->location_id);
            $this->assertEquals('ST', $asset->borrowing_type);
            $this->assertEquals('Inventaris', $asset->classification);
        }
    }
}
