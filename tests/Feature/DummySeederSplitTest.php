<?php

namespace Tests\Feature;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Database\Seeders\DummyConsumableDatabaseSeeder;
use Database\Seeders\DummyNonConsumableShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DummySeederSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumable_seeder_creates_consumable_records_and_no_units(): void
    {
        $this->seed(DummyConsumableDatabaseSeeder::class);

        // Category ATK must exist
        $atkCategory = Category::where('code', 'ATK')->first();
        $this->assertNotNull($atkCategory);

        // Subcategories must be flagged as consumable
        $subcategories = Subcategory::where('category_id', $atkCategory->id)->get();
        $this->assertCount(2, $subcategories);
        foreach ($subcategories as $sub) {
            $this->assertTrue((bool)$sub->is_consumable);
        }

        // Consumable barangs must exist and have is_consumable = true
        $hvsBarang = Barang::where('number', 'ATK-HVS4-0001')->first();
        $this->assertNotNull($hvsBarang);
        $this->assertTrue($hvsBarang->is_consumable);

        $penBarang = Barang::where('number', 'ATK-PLPH-0001')->first();
        $this->assertNotNull($penBarang);
        $this->assertTrue($penBarang->is_consumable);

        // Consumable lots must exist with quantity > 0, image_url, location_id, and unit_price
        $lots = Lot::whereIn('number', [
            'LOT-0001-26-ATK-HVS4-0001',
            'LOT-0002-26-ATK-HVS4-0001',
            'LOT-0001-26-ATK-PLPH-0001',
            'LOT-0002-26-ATK-PLPH-0002',
        ])->get();
        $this->assertCount(4, $lots);

        foreach ($lots as $lot) {
            $this->assertGreaterThan(0, $lot->initial_quantity);
            $this->assertGreaterThan(0, $lot->current_quantity);
            $this->assertNotNull($lot->burden);
            $this->assertNotNull($lot->location_id);
            $this->assertNotNull($lot->unit_price);
            $this->assertNotNull($lot->image_url);
            $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($lot->image_url));
        }

        // Consumables must NEVER generate unit records
        $this->assertEquals(0, Unit::count());
    }

    public function test_consumable_seeder_is_idempotent(): void
    {
        $this->seed(DummyConsumableDatabaseSeeder::class);
        $categoryCount1 = Category::where('code', 'ATK')->count();
        $subcatCount1 = Subcategory::where('code', 'like', 'ATK%')->count();
        $barangCount1 = Barang::where('number', 'like', 'ATK%')->count();
        $lotCount1 = Lot::where('number', 'like', '%ATK%')->count();

        // Seed second time
        $this->seed(DummyConsumableDatabaseSeeder::class);

        $this->assertEquals($categoryCount1, Category::where('code', 'ATK')->count());
        $this->assertEquals($subcatCount1, Subcategory::where('code', 'like', 'ATK%')->count());
        $this->assertEquals($barangCount1, Barang::where('number', 'like', 'ATK%')->count());
        $this->assertEquals($lotCount1, Lot::where('number', 'like', '%ATK%')->count());
        $this->assertEquals(0, Unit::count());
    }

    public function test_non_consumable_showcase_seeder_creates_showcase_records(): void
    {
        $this->seed(DummyNonConsumableShowcaseSeeder::class);

        $dgxModel = Barang::where('number', DummyNonConsumableShowcaseSeeder::SHOWCASE_DGX_MODEL_NUMBER)->first();
        $this->assertNotNull($dgxModel);
        $this->assertFalse($dgxModel->is_consumable);
        $this->assertEquals('Nvidia DGX Spark', $dgxModel->name);
        $this->assertNull($dgxModel->specification, 'COMP category model specification must be null');

        $dgxLot = Lot::where('number', DummyNonConsumableShowcaseSeeder::SHOWCASE_DGX_LOT_NUMBER)->first();
        $this->assertNotNull($dgxLot);
        $this->assertEquals(2, $dgxLot->initial_quantity);
        $this->assertNull($dgxLot->location_id, 'Non-consumable lot location_id must be null');

        $chairLot = Lot::where('number', DummyNonConsumableShowcaseSeeder::SHOWCASE_CHAIR_LOT_NUMBER)->first();
        $this->assertNotNull($chairLot);
        $this->assertEquals(5, $chairLot->initial_quantity);
        $this->assertNull($chairLot->location_id, 'Non-consumable lot location_id must be null');

        $assets = Asset::whereIn('number', DummyNonConsumableShowcaseSeeder::SHOWCASE_ASSET_NUMBERS)->get();
        $this->assertCount(7, $assets);

        // DGX Unit 1
        $dgx1 = $assets->firstWhere('number', '00001-COMP-SVR-ICT-PTRE-26');
        $this->assertNotNull($dgx1);
        $this->assertEquals('Tersedia', $dgx1->status);
        $this->assertNull($dgx1->approval_status);
        $this->assertEquals('AI Supercomputer Server, 8x NVIDIA H100 GPU', $dgx1->comp_specification);

        // DGX Unit 2 (Pending activation: Belum Diverifikasi)
        $dgx2 = $assets->firstWhere('number', '00002-COMP-SVR-ICT-PTRE-26');
        $this->assertNotNull($dgx2);
        $this->assertEquals('Belum Diverifikasi', $dgx2->status);
        $this->assertNull($dgx2->approval_status);
        $this->assertEquals('AI Supercomputer Server, 8x NVIDIA H100 GPU', $dgx2->comp_specification);

        // Chair Unit 2 (Pending DM deactivation: Rusak Total)
        $chair2 = $assets->firstWhere('number', '00102-FUR-KK-CFS-PTRE-26');
        $this->assertNotNull($chair2);
        $this->assertEquals('Tersedia', $chair2->status);
        $this->assertEquals('Rusak Total', $chair2->condition);
        $this->assertEquals('Pending:DM', $chair2->approval_status);

        // Chair Unit 4 (Approved BoD/BoC deactivation: Dihapus, Rusak Total)
        $chair4 = $assets->firstWhere('number', '00104-FUR-KK-CFS-PTRE-26');
        $this->assertNotNull($chair4);
        $this->assertEquals('Dihapus', $chair4->status);
        $this->assertEquals('Rusak Total', $chair4->condition);
        $this->assertNull($chair4->approval_status);

        $activationApprovals = AssetActivationApproval::whereHas('asset', fn ($q) => $q->whereIn('number', DummyNonConsumableShowcaseSeeder::SHOWCASE_ASSET_NUMBERS))->get();
        $this->assertCount(2, $activationApprovals);

        $deactivationApprovals = AssetDeletionApproval::whereHas('asset', fn ($q) => $q->whereIn('number', DummyNonConsumableShowcaseSeeder::SHOWCASE_ASSET_NUMBERS))->get();
        $this->assertCount(4, $deactivationApprovals);

        // Verify proposed_condition on deletion approvals
        $chair2Da = $deactivationApprovals->firstWhere('asset_id', $chair2->id);
        $this->assertNotNull($chair2Da);
        $this->assertEquals('Rusak Total', $chair2Da->proposed_condition);

        $chair4Da = $deactivationApprovals->firstWhere('asset_id', $chair4->id);
        $this->assertNotNull($chair4Da);
        $this->assertEquals('Rusak Total', $chair4Da->proposed_condition);

        $chair5 = $assets->firstWhere('number', '00105-FUR-KK-CFS-PTRE-26');
        $this->assertNotNull($chair5);
        $chair5Da = $deactivationApprovals->firstWhere('asset_id', $chair5->id);
        $this->assertNotNull($chair5Da);
        $this->assertEquals('Rusak Total', $chair5Da->proposed_condition);

        // Verify Input InventoryLog notes match actual feature format
        $dgxInboundLog = InventoryLog::where('lot_id', $dgxLot->id)->where('action_type', 'stock_in')->first();
        $this->assertNotNull($dgxInboundLog);
        $this->assertEquals('Penerimaan LOT baru sebanyak 2 Unit (PO: PO-ICT-2026-DGX01)', $dgxInboundLog->note);

        $chairInboundLog = InventoryLog::where('lot_id', $chairLot->id)->where('action_type', 'stock_in')->first();
        $this->assertNotNull($chairInboundLog);
        $this->assertEquals('Penerimaan LOT baru sebanyak 5 Unit (Memo: MEMO-CFS-2026-HB01)', $chairInboundLog->note);

        // Verify Asset Registration & Activation Lifecycle notes
        $dgx1Lifecycles = AssetLifecycle::where('asset_id', $dgx1->id)->orderBy('id')->get();
        $this->assertCount(2, $dgx1Lifecycles);
        $this->assertEquals('Registrasi', $dgx1Lifecycles[0]->action_type);
        $this->assertEquals('Aset berhasil diregistrasi ke dalam sistem.', $dgx1Lifecycles[0]->note);
        $this->assertNotNull($dgx1Lifecycles[0]->end_date);
        $this->assertEquals('Approval', $dgx1Lifecycles[1]->action_type);
        $this->assertEquals('Aktivasi aset disetujui oleh DM IFS. Catatan: Disetujui untuk operasional AI Research', $dgx1Lifecycles[1]->note);
        $this->assertNull($dgx1Lifecycles[1]->end_date);

        $dgx2Lifecycles = AssetLifecycle::where('asset_id', $dgx2->id)->get();
        $this->assertCount(1, $dgx2Lifecycles);
        $this->assertEquals('Registrasi', $dgx2Lifecycles[0]->action_type);
        $this->assertEquals('Aset berhasil diregistrasi ke dalam sistem.', $dgx2Lifecycles[0]->note);
        $this->assertNull($dgx2Lifecycles[0]->end_date);

        $chair1 = $assets->firstWhere('number', '00101-FUR-KK-CFS-PTRE-26');
        $this->assertNotNull($chair1);
        $chair1Lifecycles = AssetLifecycle::where('asset_id', $chair1->id)->orderBy('id')->get();
        $this->assertCount(2, $chair1Lifecycles);
        $this->assertEquals('Registrasi', $chair1Lifecycles[0]->action_type);
        $this->assertEquals('Aset berhasil diregistrasi ke dalam sistem.', $chair1Lifecycles[0]->note);
        $this->assertNotNull($chair1Lifecycles[0]->end_date);
        $this->assertEquals('Approval', $chair1Lifecycles[1]->action_type);
        $this->assertEquals('Aktivasi aset disetujui oleh DM IFS. Catatan: Disetujui untuk operasional kantor', $chair1Lifecycles[1]->note);
        $this->assertNull($chair1Lifecycles[1]->end_date);

        // Verify Deactivation Lifecycle notes
        $chair2Lifecycles = AssetLifecycle::where('asset_id', $chair2->id)->get();
        $this->assertCount(1, $chair2Lifecycles, 'Chair 2 should only have 1 lifecycle entry without duplicates');
        $this->assertEquals('Perubahan kondisi', $chair2Lifecycles[0]->action_type);
        $this->assertEquals('Pengajuan deaktivasi kursi kerja rusak hidrolik dan patah kaki', $chair2Lifecycles[0]->note);
        $this->assertNull($chair2Lifecycles[0]->end_date);

        $chair3 = $assets->firstWhere('number', '00103-FUR-KK-CFS-PTRE-26');
        $this->assertNotNull($chair3);
        $chair3Lifecycles = AssetLifecycle::where('asset_id', $chair3->id)->orderBy('id')->get();
        $this->assertCount(2, $chair3Lifecycles);
        $this->assertEquals('Perubahan kondisi', $chair3Lifecycles[0]->action_type);
        $this->assertEquals('Pengajuan penghapusan karena unit kursi hilang saat renovasi ruangan', $chair3Lifecycles[0]->note);
        $this->assertNotNull($chair3Lifecycles[0]->end_date);
        $this->assertEquals('Persetujuan', $chair3Lifecycles[1]->action_type);
        $this->assertEquals('Disetujui untuk diteruskan ke BoD/BoC', $chair3Lifecycles[1]->note);
        $this->assertNull($chair3Lifecycles[1]->end_date);

        $chair4Lifecycles = AssetLifecycle::where('asset_id', $chair4->id)->get();
        $this->assertCount(1, $chair4Lifecycles);
        $this->assertEquals('Persetujuan', $chair4Lifecycles[0]->action_type);
        $this->assertEquals('BoD/BoC menyetujui penghapusan asset dengan kondisi Rusak Total', $chair4Lifecycles[0]->note);
        $this->assertNull($chair4Lifecycles[0]->end_date);

        $chair5Lifecycles = AssetLifecycle::where('asset_id', $chair5->id)->get();
        $this->assertCount(1, $chair5Lifecycles);
        $this->assertEquals('Penolakan', $chair5Lifecycles[0]->action_type);
        $this->assertEquals('BoD/BoC menolak penghapusan asset dengan kondisi Rusak Total', $chair5Lifecycles[0]->note);
        $this->assertNull($chair5Lifecycles[0]->end_date);

        // Verify each showcase asset has at most one active lifecycle
        foreach ($assets as $asset) {
            $activeCount = AssetLifecycle::where('asset_id', $asset->id)->whereNull('end_date')->count();
            $this->assertLessThanOrEqual(1, $activeCount, "Asset {$asset->number} has more than one active lifecycle");
        }
    }
}
