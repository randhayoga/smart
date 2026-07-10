<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Barang;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\Barang>
 */
class BarangFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Barang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subcategory = Subcategory::inRandomOrder()->first() ?? Subcategory::factory()->create();
        $subcatCode = $subcategory->code;
        
        static $barangSerial = 1;
        $serial = str_pad($barangSerial++, 4, '0', STR_PAD_LEFT);
        $number = "{$subcatCode}-{$serial}";

        return [
            'number' => $number,
            'subcategory_id' => $subcategory->id,
            'brand_id' => Brand::inRandomOrder()->first() ?? Brand::factory(),
            'uom_id' => Uom::inRandomOrder()->first() ?? Uom::factory(),
            'name' => $this->faker->words(3, true),
            'specification' => $this->faker->sentence(),
            'image_url' => 'inventory/barangs/placeholder.jpg',
        ];
    }
}
