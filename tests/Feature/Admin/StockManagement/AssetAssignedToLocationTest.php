<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
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

class AssetAssignedToLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Lot $lot;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = Category::factory()->create([
            'code' => 'ELEC',
            'name' => 'Elektronik',
        ]);
        $type = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'ELEC-TV',
            'name' => 'Televisi',
            'is_consumable' => false,
        ]);
        $brand = Brand::factory()->create(['name' => 'Sony']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $organizer = Organizer::factory()->create(['name' => 'DIV-GA']);
        $this->location = Location::factory()->create();
        $vendor = Vendor::factory()->create(['name' => 'PT Mitra Sejahtera']);

        $model = InventoryModel::factory()->create([
            'number' => 'ELEC-TV-0001',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $this->lot = Lot::factory()->create([
            'number' => 'LOT-TV-001',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_single_asset_update_assigns_to_location_and_updates_status_to_di_assign_lt(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $asset->fresh();
        $this->assertTrue($fresh->is_assigned_to_location);
        $this->assertSame('Di-assign (LT)', $fresh->status);

        // Verify lifecycle entry
        $lifecycles = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'assignment')
            ->get();
        $this->assertCount(1, $lifecycles);
        $log = $lifecycles->first();
        $this->assertSame('assignment', $log->action_type);
        $this->assertSame('Di-assign (LT)', $log->status);
        $this->assertSame('Bagus', $log->condition);
        $this->assertSame('Aset ditetapkan ke lokasi dengan status Di-assign (LT).', $log->note);
        $this->assertSame($this->user->id, $log->actor_id);
        $this->assertNull($log->end_date);

        // Verify no duplicate 'Perubahan status' log was created
        $statusLogs = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Perubahan status')
            ->count();
        $this->assertSame(0, $statusLogs);
    }

    public function test_single_asset_update_unassigns_from_location_and_reverts_status_to_tersedia(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $asset->fresh();
        $this->assertFalse($fresh->is_assigned_to_location);
        $this->assertSame('Tersedia', $fresh->status);

        // Verify assignment unassign lifecycle entry
        $log = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'assignment')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('Tersedia', $log->status);
        $this->assertSame('Penetapan aset ke lokasi dibatalkan (status kembali Tersedia).', $log->note);
    }

    public function test_single_asset_update_fails_if_status_is_not_available_when_assigning(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-03',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Dipinjam',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $response->assertSessionHasErrors(['is_assigned_to_location']);
        $this->assertFalse($asset->fresh()->is_assigned_to_location);
        $this->assertSame('Dipinjam', $asset->fresh()->status);
    }

    public function test_bulk_asset_update_assigns_all_selected_available_assets(): void
    {
        $asset1 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'is_assigned_to_location' => false,
        ]);
        $asset2 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'is_assigned_to_location' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($asset1->fresh()->is_assigned_to_location);
        $this->assertSame('Di-assign (LT)', $asset1->fresh()->status);
        $this->assertTrue($asset2->fresh()->is_assigned_to_location);
        $this->assertSame('Di-assign (LT)', $asset2->fresh()->status);

        $this->assertSame(1, AssetLifecycle::where('asset_id', $asset1->id)->where('action_type', 'assignment')->count());
        $this->assertSame(1, AssetLifecycle::where('asset_id', $asset2->id)->where('action_type', 'assignment')->count());
    }

    public function test_bulk_asset_update_fails_if_any_selected_asset_is_not_available(): void
    {
        $asset1 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'is_assigned_to_location' => false,
        ]);
        $asset2 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Dipinjam',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'is_assigned_to_location' => true,
        ]);

        $response->assertSessionHasErrors(['is_assigned_to_location']);
        $this->assertFalse($asset1->fresh()->is_assigned_to_location);
        $this->assertSame('Tersedia', $asset1->fresh()->status);
        $this->assertFalse($asset2->fresh()->is_assigned_to_location);
        $this->assertSame('Dipinjam', $asset2->fresh()->status);
    }

    public function test_bulk_asset_update_unassigns_assets_reverting_di_assign_lt_to_tersedia(): void
    {
        $asset1 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'is_assigned_to_location' => true,
        ]);
        $asset2 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'is_assigned_to_location' => true,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'is_assigned_to_location' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($asset1->fresh()->is_assigned_to_location);
        $this->assertSame('Tersedia', $asset1->fresh()->status);
        $this->assertFalse($asset2->fresh()->is_assigned_to_location);
        $this->assertSame('Tersedia', $asset2->fresh()->status);
    }
}
