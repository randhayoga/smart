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
        return [
            'number' => $this->faker->unique()->bothify('???-???-####'),
            'subcategory_id' => Subcategory::factory(),
            'brand_id' => Brand::factory(),
            'uom_id' => Uom::factory(),
            'name' => $this->faker->words(3, true),
            'specification' => $this->faker->sentence(),
            'image_url' => 'inventory/barangs/placeholder.jpg',
        ];
    }
}
