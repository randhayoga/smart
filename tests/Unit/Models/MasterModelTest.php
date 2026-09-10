<?php

namespace Tests\Unit\Models;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Data Model Unit Tests
 *
 * Verifies relationships across master data hierarchies (Category/Subcategory, Location Hierarchy, Brand/UOM, Organizer/Vendor).
 */
class MasterModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_subcategory_relationships(): void
    {
        $category = Category::factory()->create(['name' => 'Elektronik']);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
            'name' => 'Laptop',
        ]);

        $this->assertTrue($category->subcategories->contains($subcategory));
        $this->assertTrue($subcategory->category->is($category));
    }

    public function test_location_hierarchy_relationships_and_full_name(): void
    {
        $building = Location::factory()->create(['name' => 'Graha RE 1', 'parent_id' => null]);
        $floor = Location::factory()->create(['name' => 'Lantai Mezzanine', 'parent_id' => $building->id]);
        $room = Location::factory()->create(['name' => 'Ruang IFS Departemen', 'parent_id' => $floor->id]);

        $this->assertTrue($building->children->contains($floor));
        $this->assertTrue($floor->parent->is($building));
        $this->assertTrue($floor->children->contains($room));
        $this->assertTrue($room->parent->is($floor));

        $this->assertEquals('Graha RE 1', $building->full_name);
        $this->assertEquals('Graha RE 1, Lantai Mezzanine', $floor->full_name);
        $this->assertEquals('Graha RE 1, Lantai Mezzanine, Ruang IFS Departemen', $room->full_name);
        
        $this->assertEquals([$floor->id, $room->id], $building->allChildrenIds());
    }

    public function test_brand_and_uom_relationships_to_barang(): void
    {
        $brand = Brand::factory()->create(['name' => 'Dell']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $barang = Barang::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $this->assertTrue($brand->barangs->contains($barang));
        $this->assertTrue($uom->barangs->contains($barang));
    }

    public function test_organizer_and_vendor_relationships_to_lot(): void
    {
        $organizer = Organizer::factory()->create(['name' => 'IT Dept']);
        $vendor = Vendor::factory()->create(['name' => 'PT Tech Vendor']);
        $lot = Lot::factory()->create([
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
        ]);

        $this->assertTrue($organizer->lots->contains($lot));
        $this->assertTrue($vendor->lots->contains($lot));
    }
}
