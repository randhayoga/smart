<?php

namespace Tests\Feature\Admin\ManajemenStok;

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
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitNumberSerializationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Location $location;
    private Organizer $organizerCfs;
    private Organizer $organizerIct;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->location = Location::factory()->create();
        $this->organizerCfs = Organizer::firstOrCreate(['name' => 'CFS']);
        $this->organizerIct = Organizer::firstOrCreate(['name' => 'ICT']);
    }

    private function createLot(Organizer $organizer, string $subcatCode, string $receiptDate = '2026-06-01'): Lot
    {
        $category = Category::factory()->create(['name' => 'Aset']);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => $subcatCode,
            'name' => $subcatCode,
        ]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'number' => "{$subcatCode}-0001",
            'image_url' => 'inventory/barangs/sample.jpg',
        ]);

        Storage::disk('local')->put('inventory/barangs/sample.jpg', 'content');
        Storage::disk('local')->put('inventory/lots/sample.jpg', 'content');

        return Lot::create([
            'number' => "LOT-0001-26-{$barang->number}",
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => Vendor::factory()->create()->id,
            'location_id' => $this->location->id,
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'po_number' => 'PO-01',
            'date_of_receipt' => Carbon::parse($receiptDate),
            'unit_price' => 250000,
            'image_url' => 'inventory/lots/sample.jpg',
            'burden' => 'Corporate',
        ]);
    }

    public function test_single_unit_store_increments_by_organizer_across_subcategories(): void
    {
        $lotFur = $this->createLot($this->organizerCfs, 'FUR-KK', '2026-01-15');

        // Store first unit for CFS (FUR-KK)
        $response1 = $this->actingAs($this->user)->post(route('smart.inventory.units.store'), [
            'lot_id' => $lotFur->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'use_lot_image' => '1',
            'price' => 250000,
        ]);

        $response1->assertRedirect();
        $response1->assertSessionHasNoErrors();

        $unit1 = Unit::where('lot_id', $lotFur->id)->first();
        $this->assertNotNull($unit1);
        $this->assertEquals('00001-FUR-KK-CFS-PTRE-26', $unit1->number);

        // Store second unit for CFS with a different subcategory (COMP-NB) and year 2027
        $lotComp = $this->createLot($this->organizerCfs, 'COMP-NB', '2027-02-20');

        $response2 = $this->actingAs($this->user)->post(route('smart.inventory.units.store'), [
            'lot_id' => $lotComp->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'use_lot_image' => '1',
            'price' => 500000,
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHasNoErrors();

        $unit2 = Unit::where('lot_id', $lotComp->id)->first();
        $this->assertNotNull($unit2);
        // Must increment from the largest CFS serial (1 -> 2)
        $this->assertEquals('00002-COMP-NB-CFS-PTRE-27', $unit2->number);
    }

    public function test_single_unit_store_is_segregated_by_organizer(): void
    {
        $lotCfs = $this->createLot($this->organizerCfs, 'FUR-KK', '2026-01-15');
        $lotIct = $this->createLot($this->organizerIct, 'COMP-NB', '2026-01-15');

        // Create a unit for CFS with serial 00005
        Unit::create([
            'number' => '00005-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lotCfs->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        // Creating a unit for ICT should start at 00001 despite CFS having 00005
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.store'), [
            'lot_id' => $lotIct->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'use_lot_image' => '1',
            'price' => 500000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $ictUnit = Unit::where('lot_id', $lotIct->id)->first();
        $this->assertNotNull($ictUnit);
        $this->assertEquals('00001-COMP-NB-ICT-PTRE-26', $ictUnit->number);
    }

    public function test_bulk_unit_store_continues_organizer_sequence(): void
    {
        $lot = $this->createLot($this->organizerCfs, 'FUR-KK', '2026-01-15');

        // Existing unit at serial 00002
        Unit::create([
            'number' => '00002-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ]);

        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.bulk-store'), [
            'number' => 'preview-placeholder',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'use_lot_image' => '1',
            'bulk_quantity' => 3,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $numbers = Unit::where('lot_id', $lot->id)
            ->where('number', '!=', '00002-FUR-KK-CFS-PTRE-26')
            ->orderBy('number')
            ->pluck('number')
            ->toArray();

        $this->assertEquals([
            '00003-FUR-KK-CFS-PTRE-26',
            '00004-FUR-KK-CFS-PTRE-26',
            '00005-FUR-KK-CFS-PTRE-26',
        ], $numbers);
    }
}
