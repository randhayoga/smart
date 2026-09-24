<?php

namespace Tests\Unit\Services;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Services\Inventory\UnitNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UnitNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitNumberService();
    }

    private function createLot(string $organizerName, string $subcategoryCode, ?string $receiptDate = '2026-05-15'): Lot
    {
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => $subcategoryCode,
        ]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'number' => "{$subcategoryCode}-0001",
        ]);

        $organizer = Organizer::firstOrCreate(['name' => $organizerName]);
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();

        return Lot::create([
            'number' => "LOT-0001-26-{$barang->number}",
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'po_number' => 'PO-01',
            'date_of_receipt' => $receiptDate ? Carbon::parse($receiptDate) : null,
            'unit_price' => 100000,
            'image_url' => 'inventory/lots/placeholder.jpg',
            'burden' => 'Corporate',
        ]);
    }

    public function test_get_next_serial_returns_1_when_no_units_exist_for_organizer(): void
    {
        $this->assertEquals(1, $this->service->getNextSerial('CFS'));
        $this->assertEquals(1, $this->service->getNextSerial(''));
    }

    public function test_get_next_serial_finds_increment_of_largest_unit_for_organizer(): void
    {
        $lot = $this->createLot('CFS', 'FUR-KK');

        Unit::create([
            'number' => '00001-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        // Gap intentionally left (no 00002, 00003)
        Unit::create([
            'number' => '00004-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        $this->assertEquals(5, $this->service->getNextSerial('CFS'));
    }

    public function test_get_next_serial_is_scoped_strictly_by_organizer(): void
    {
        $cfsLot = $this->createLot('CFS', 'FUR-KK');
        $ictLot = $this->createLot('ICT', 'COMP-NB');

        Unit::create([
            'number' => '00010-COMP-NB-ICT-PTRE-26',
            'lot_id' => $ictLot->id,
            'location_id' => $ictLot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        Unit::create([
            'number' => '00002-FUR-KK-CFS-PTRE-26',
            'lot_id' => $cfsLot->id,
            'location_id' => $cfsLot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        $this->assertEquals(3, $this->service->getNextSerial('CFS'));
        $this->assertEquals(11, $this->service->getNextSerial('ICT'));
        $this->assertEquals(1, $this->service->getNextSerial('HSE'));
    }

    public function test_generate_unit_number_continues_serial_across_different_subcategories_and_years(): void
    {
        $lotFur2026 = $this->createLot('CFS', 'FUR-KK', '2026-01-10');
        $unit1Number = $this->service->generateUnitNumber($lotFur2026);
        $this->assertEquals('00001-FUR-KK-CFS-PTRE-26', $unit1Number);

        Unit::create([
            'number' => $unit1Number,
            'lot_id' => $lotFur2026->id,
            'location_id' => $lotFur2026->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        // Second lot has a different subcategory (COMP-NB) and different year (2027), but same organizer (CFS)
        $lotComp2027 = $this->createLot('CFS', 'COMP-NB', '2027-03-20');
        $unit2Number = $this->service->generateUnitNumber($lotComp2027);

        // Must continue sequence (00002) rather than resetting to 00001
        $this->assertEquals('00002-COMP-NB-CFS-PTRE-27', $unit2Number);
    }

    public function test_generate_bulk_unit_numbers_produces_continuous_sequence(): void
    {
        $lot = $this->createLot('CFS', 'FUR-KK', '2026-06-01');

        Unit::create([
            'number' => '00003-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        $generated = $this->service->generateBulkUnitNumbers($lot, 3);

        $this->assertCount(3, $generated);
        $this->assertEquals([
            '00004-FUR-KK-CFS-PTRE-26',
            '00005-FUR-KK-CFS-PTRE-26',
            '00006-FUR-KK-CFS-PTRE-26',
        ], $generated);
    }
}
