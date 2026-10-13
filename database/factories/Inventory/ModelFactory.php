<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Model;
use App\Models\Master\Type;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\Model>
 */
class ModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Model::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = Type::inRandomOrder()->first() ?? Type::factory()->create();
        $typeCode = $type->code;
        
        static $modelSerial = 1;
        $serial = str_pad($modelSerial++, 4, '0', STR_PAD_LEFT);
        $number = "{$typeCode}-{$serial}";

        return [
            'number' => $number,
            'type_id' => $type->id,
            'brand_id' => Brand::inRandomOrder()->first() ?? Brand::factory(),
            'uom_id' => Uom::inRandomOrder()->first() ?? Uom::factory(),
            'name' => $this->faker->words(3, true),
            'specification' => $this->faker->sentence(),
            'image_url' => 'inventory/models/placeholder.jpg',
        ];
    }
}
