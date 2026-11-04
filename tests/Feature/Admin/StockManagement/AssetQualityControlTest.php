<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetQualityControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Lot $lot;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $organizer = Organizer::firstOrCreate(['name' => 'IT Dept'], ['code' => 'IT']);
        $category = Category::firstOrCreate(['name' => 'Electronic'], ['code' => 'ELC']);
        $type = Type::firstOrCreate(['name' => 'Display', 'category_id' => $category->id], ['code' => 'DSP']);
        $brand = Brand::firstOrCreate(['name' => 'Dell']);
        $uom = Uom::firstOrCreate(['name' => 'Unit']);
        $this->location = Location::firstOrCreate(['name' => 'HQ Floor 1'], ['full_name' => 'HQ - Floor 1']);

        $model = InventoryModel::create([
            'number' => 'MDL-QC-001',
            'name' => 'UltraSharp 27',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'is_consumable' => false,
        ]);

        $this->lot = Lot::create([
            'number' => 'LOT-QC-001',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'condition' => 'Baik',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'purchase_grant_date' => now(),
        ]);
    }

    public function test_can_create_asset_with_qc_pass_true_bundles_registration_log(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/stock-management/assets', [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'type' => 'LT',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'qc_pass' => true,
        ]);

        $response->assertRedirect();

        $asset = Asset::where('lot_id', $this->lot->id)->first();
        $this->assertNotNull($asset);
        $this->assertTrue($asset->qc_pass);
        $this->assertNotNull($asset->last_qc);
        $this->assertEquals(now()->format('Y-m-d'), $asset->last_qc->format('Y-m-d'));

        $log = AssetLifecycle::where('asset_id', $asset->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('Registrasi', $log->action_type);
        $this->assertEquals('Aset berhasil diregistrasi ke dalam sistem. QC Dilakukan, hasil: QC Pass.', $log->note);
        $this->assertIsArray($log->new_state);
        $this->assertTrue($log->new_state['qc_pass']);
    }

    public function test_can_create_asset_without_qc_pass_leaves_nullable_and_standard_log(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/stock-management/assets', [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'type' => 'LT',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();

        $asset = Asset::where('lot_id', $this->lot->id)->first();
        $this->assertNotNull($asset);
        $this->assertNull($asset->qc_pass);
        $this->assertNull($asset->last_qc);

        $log = AssetLifecycle::where('asset_id', $asset->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('Registrasi', $log->action_type);
        $this->assertEquals('Aset berhasil diregistrasi ke dalam sistem.', $log->note);
    }

    public function test_can_update_asset_qc_pass_from_null_to_fail_logs_lifecycle(): void
    {
        $asset = Asset::create([
            'number' => 'AST-QC-0001',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => null,
            'last_qc' => null,
        ]);

        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->id), [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'qc_pass' => false,
        ]);

        $response->assertRedirect();

        $asset->refresh();
        $this->assertFalse($asset->qc_pass);
        $this->assertNotNull($asset->last_qc);
        $this->assertEquals(now()->format('Y-m-d'), $asset->last_qc->format('Y-m-d'));

        $qcLog = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Quality Control')
            ->first();
        $this->assertNotNull($qcLog);
        $this->assertEquals('QC Dilakukan, hasil: QC Fail', $qcLog->note);
        $this->assertNull($qcLog->previous_state['qc_pass']);
        $this->assertFalse($qcLog->new_state['qc_pass']);
    }

    public function test_can_re_qc_asset_with_same_status_updates_last_qc_and_logs(): void
    {
        $yesterday = Carbon::yesterday()->startOfDay();
        $asset = Asset::create([
            'number' => 'AST-QC-0002',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => true,
            'last_qc' => $yesterday,
        ]);

        // Submit another QC Pass (same status as before)
        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->id), [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'qc_pass' => true,
        ]);

        $response->assertRedirect();

        $asset->refresh();
        $this->assertTrue($asset->qc_pass);
        $this->assertEquals(now()->format('Y-m-d'), $asset->last_qc->format('Y-m-d'));

        $qcLog = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Quality Control')
            ->first();
        $this->assertNotNull($qcLog);
        $this->assertEquals('QC Dilakukan, sebelum: QC Pass, sesudah: QC Pass', $qcLog->note);
        $this->assertTrue($qcLog->previous_state['qc_pass']);
        $this->assertTrue($qcLog->new_state['qc_pass']);
    }

    public function test_can_update_qc_status_from_pass_to_fail_logs_lifecycle(): void
    {
        $asset = Asset::create([
            'number' => 'AST-QC-0003',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => true,
            'last_qc' => Carbon::yesterday(),
        ]);

        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->id), [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'qc_pass' => false,
        ]);

        $response->assertRedirect();

        $asset->refresh();
        $this->assertFalse($asset->qc_pass);

        $qcLog = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Quality Control')
            ->first();
        $this->assertNotNull($qcLog);
        $this->assertEquals('QC Dilakukan, sebelum: QC Pass, sesudah: QC Fail', $qcLog->note);
        $this->assertTrue($qcLog->previous_state['qc_pass']);
        $this->assertFalse($qcLog->new_state['qc_pass']);
    }

    public function test_updating_asset_without_touching_qc_preserves_existing_qc_and_does_not_log_qc(): void
    {
        $yesterday = Carbon::yesterday()->startOfDay();
        $asset = Asset::create([
            'number' => 'AST-QC-0004',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => true,
            'last_qc' => $yesterday,
        ]);

        // Submit update without qc_pass (omitted as Unchanged / null)
        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->id), [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Rusak',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();

        $asset->refresh();
        $this->assertTrue($asset->qc_pass);
        $this->assertEquals($yesterday->format('Y-m-d H:i:s'), $asset->last_qc->format('Y-m-d H:i:s'));

        // Only condition change was logged, zero QC lifecycles
        $this->assertEquals(0, AssetLifecycle::where('asset_id', $asset->id)->where('action_type', 'Quality Control')->count());
        $condLog = AssetLifecycle::where('asset_id', $asset->id)->where('action_type', 'Perubahan kondisi')->first();
        $this->assertNotNull($condLog);
    }

    public function test_asset_detail_endpoint_returns_qc_pass_and_last_qc(): void
    {
        $asset = Asset::create([
            'number' => 'AST-QC-0005',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => true,
            'last_qc' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('smart.inventory.assets.show', $asset->id));

        $response->assertOk();
        $response->assertJson([
            'id' => $asset->id,
            'qc_pass' => true,
            'last_qc_formatted' => now()->format('d-m-Y'),
        ]);
    }

    public function test_bulk_update_assets_updates_qc_pass_and_logs_lifecycle(): void
    {
        $asset1 = Asset::create([
            'number' => 'AST-QC-0006',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => null,
            'last_qc' => null,
        ]);

        $asset2 = Asset::create([
            'number' => 'AST-QC-0007',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'LT',
            'burden' => 'Corporate',
            'qc_pass' => true,
            'last_qc' => Carbon::yesterday(),
        ]);

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'qc_pass' => false,
        ]);

        $response->assertRedirect();

        $asset1->refresh();
        $asset2->refresh();

        $this->assertFalse($asset1->qc_pass);
        $this->assertNotNull($asset1->last_qc);
        $this->assertFalse($asset2->qc_pass);
        $this->assertNotNull($asset2->last_qc);

        // Verify lifecycle logs
        $log1 = AssetLifecycle::where('asset_id', $asset1->id)->where('action_type', 'Quality Control')->first();
        $this->assertNotNull($log1);
        $this->assertEquals('QC Dilakukan, hasil: QC Fail', $log1->note);

        $log2 = AssetLifecycle::where('asset_id', $asset2->id)->where('action_type', 'Quality Control')->first();
        $this->assertNotNull($log2);
        $this->assertEquals('QC Dilakukan, sebelum: QC Pass, sesudah: QC Fail', $log2->note);
    }
}
