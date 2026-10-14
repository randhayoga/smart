<?php

namespace Tests\Unit\Services;

use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Services\Inventory\AssetNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AssetNumberService $service;
    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssetNumberService();
        $this->location = Location::factory()->create();
    }

    private function createLot(string $organizerName, string $typeCode, ?string $receiptDate = '2026-05-15'): Lot
    {
        $category = Category::factory()->create();
        $type = Type::factory()->create([
            'category_id' => $category->id,
            'code' => $typeCode,
        ]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $model = Model::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'number' => "{$typeCode}-0001",
        ]);

        $organizer = Organizer::firstOrCreate(['name' => $organizerName]);
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();

        return Lot::create([
            'number' => "LOT-0001-26-{$model->number}",
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'po_number' => 'PO-01',
            'purchase_grant_date' => $receiptDate ? Carbon::parse($receiptDate) : null,
            'burden' => 'Corporate',
        ]);
    }

    public function test_get_next_serial_returns_1_when_no_assets_exist_for_organizer(): void
    {
        $this->assertEquals(1, $this->service->getNextSerial('CFS'));
        $this->assertEquals(1, $this->service->getNextSerial(''));
    }

    public function test_get_next_serial_finds_increment_of_largest_asset_for_organizer(): void
    {
        $lot = $this->createLot('CFS', 'FUR-KK');

        Asset::create([
            'number' => '00001-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'assets/sample.jpg',
        ]);

        // Gap intentionally left (no 00002, 00003)
        Asset::create([
            'number' => '00004-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'assets/sample.jpg',
        ]);

        $this->assertEquals(5, $this->service->getNextSerial('CFS'));
    }

    public function test_get_next_serial_is_scoped_strictly_by_organizer(): void
    {
        $cfsLot = $this->createLot('CFS', 'FUR-KK');
        $ictLot = $this->createLot('ICT', 'COMP-NB');

        Asset::create([
            'number' => '00010-COMP-NB-ICT-PTRE-26',
            'lot_id' => $ictLot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'assets/sample.jpg',
        ]);

        Asset::create([
            'number' => '00002-FUR-KK-CFS-PTRE-26',
            'lot_id' => $cfsLot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'assets/sample.jpg',
        ]);

        $this->assertEquals(3, $this->service->getNextSerial('CFS'));
        $this->assertEquals(11, $this->service->getNextSerial('ICT'));
        $this->assertEquals(1, $this->service->getNextSerial('HSE'));
    }

    public function test_generate_asset_number_continues_serial_across_different_types_and_years(): void
    {
        $lotFur2026 = $this->createLot('CFS', 'FUR-KK', '2026-01-10');
        $asset1Number = $this->service->generateAssetNumber($lotFur2026);
        $this->assertEquals('00001-FUR-KK-CFS-PTRE-26', $asset1Number);

        Asset::create([
            'number' => $asset1Number,
            'lot_id' => $lotFur2026->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'assets/sample.jpg',
        ]);

        // Second lot has a different type (COMP-NB) and different year (2027), but same organizer (CFS)
        $lotComp2027 = $this->createLot('CFS', 'COMP-NB', '2027-03-20');
        $asset2Number = $this->service->generateAssetNumber($lotComp2027);

        // Must continue sequence (00002) rather than resetting to 00001
        $this->assertEquals('00002-COMP-NB-CFS-PTRE-27', $asset2Number);
    }

    public function test_generate_bulk_asset_numbers_produces_continuous_sequence(): void
    {
        $lot = $this->createLot('CFS', 'FUR-KK', '2026-06-01');

        Asset::create([
            'number' => '00003-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'assets/sample.jpg',
        ]);

        $generated = $this->service->generateBulkAssetNumbers($lot, 3);

        $this->assertCount(3, $generated);
        $this->assertEquals([
            '00004-FUR-KK-CFS-PTRE-26',
            '00005-FUR-KK-CFS-PTRE-26',
            '00006-FUR-KK-CFS-PTRE-26',
        ], $generated);
    }
}
