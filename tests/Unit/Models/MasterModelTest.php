<?php

namespace Tests\Unit\Models;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_location_floor_room_relationships(): void
    {
        $location = Location::factory()->create(['name' => 'HQ']);
        $floor = Floor::factory()->create(['location_id' => $location->id, 'name' => 'Lantai 2']);
        $room = Room::factory()->create(['floor_id' => $floor->id, 'name' => 'Ruang 201']);

        $this->assertTrue($location->floors->contains($floor));
        $this->assertTrue($floor->location->is($location));
        $this->assertTrue($floor->rooms->contains($room));
        $this->assertTrue($room->floor->is($floor));
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
