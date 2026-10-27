<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetConditionDamageLostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Lot $lot;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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

    public function test_single_asset_update_to_total_damage_creates_deletion_approval_and_sets_pending_dm(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-DMG-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
        ]);

        $memoFile = UploadedFile::fake()->create('memo_rusak_total.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'type' => 'LT',
            'classification' => 'Aset',
            'memo_file' => $memoFile,
            'admin_note' => 'Kerusakan fisik parah',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertSame('Tersedia', $fresh->status);
        $this->assertSame('Pending:DM', $fresh->approval_status);
        $this->assertSame('Rusak Total', $fresh->condition);

        $approval = AssetDeletionApproval::where('asset_id', $asset->id)->first();
        $this->assertNotNull($approval);
        $this->assertSame('Rusak Total', $approval->proposed_condition);
        $this->assertSame('Bagus', $approval->previous_condition);
        $this->assertSame('Tersedia', $approval->previous_status);
        $this->assertSame('pending', $approval->decision);
        $this->assertSame('Kerusakan fisik parah', $approval->admin_note);
        $this->assertNotNull($approval->memo_url);
        $this->assertNull($approval->lost_doc_url);
    }

    public function test_single_asset_update_to_total_damage_requires_memo_file(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-DMG-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'type' => 'LT',
            'classification' => 'Aset',
        ]);

        $response->assertSessionHasErrors(['memo_file']);
        $this->assertSame(0, AssetDeletionApproval::where('asset_id', $asset->id)->count());
    }

    public function test_single_asset_update_to_lost_requires_lost_doc_file(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-LOST-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
        ]);

        $memoFile = UploadedFile::fake()->create('memo_hilang.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Hilang',
            'type' => 'LT',
            'classification' => 'Aset',
            'memo_file' => $memoFile,
            // lost_doc_file missing
        ]);

        $response->assertSessionHasErrors(['lost_doc_file']);
        $this->assertSame(0, AssetDeletionApproval::where('asset_id', $asset->id)->count());
    }

    public function test_single_asset_update_to_lost_with_required_files_creates_deletion_approval(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-LOST-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
        ]);

        $memoFile = UploadedFile::fake()->create('memo_hilang.pdf', 500, 'application/pdf');
        $lostDocFile = UploadedFile::fake()->create('surat_kehilangan.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Hilang',
            'type' => 'LT',
            'classification' => 'Aset',
            'memo_file' => $memoFile,
            'lost_doc_file' => $lostDocFile,
            'admin_note' => 'Aset hilang di site',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertSame('Tersedia', $fresh->status);
        $this->assertSame('Pending:DM', $fresh->approval_status);
        $this->assertSame('Hilang', $fresh->condition);

        $approval = AssetDeletionApproval::where('asset_id', $asset->id)->first();
        $this->assertNotNull($approval);
        $this->assertSame('Hilang', $approval->proposed_condition);
        $this->assertSame('Bagus', $approval->previous_condition);
        $this->assertSame('Tersedia', $approval->previous_status);
        $this->assertSame('pending', $approval->decision);
        $this->assertNotNull($approval->memo_url);
        $this->assertNotNull($approval->lost_doc_url);
    }

    public function test_bulk_asset_update_to_total_damage_preserves_base_status_and_creates_deletion_approvals(): void
    {
        $asset1 = Asset::factory()->create([
            'number' => 'AST-BULK-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
        ]);

        $asset2 = Asset::factory()->create([
            'number' => 'AST-BULK-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'approval_status' => null,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $memoFile = UploadedFile::fake()->create('bulk_memo.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'condition' => 'Rusak Total',
            'memo_file' => $memoFile,
            'admin_note' => 'Kerusakan massal',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $fresh1 = $asset1->fresh();
        $fresh2 = $asset2->fresh();

        $this->assertSame('Tersedia', $fresh1->status);
        $this->assertSame('Pending:DM', $fresh1->approval_status);
        $this->assertSame('Rusak Total', $fresh1->condition);

        $this->assertSame('Di-assign (LT)', $fresh2->status);
        $this->assertSame('Pending:DM', $fresh2->approval_status);
        $this->assertSame('Rusak Total', $fresh2->condition);

        $this->assertSame(2, AssetDeletionApproval::whereIn('asset_id', [$asset1->id, $asset2->id])->count());
    }
}
