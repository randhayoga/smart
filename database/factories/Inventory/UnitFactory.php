<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\Unit>
 */
class UnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'UNT-' . fake()->unique()->numerify('#####'),
            'lot_id' => Lot::factory(),
            'location_id' => \App\Models\Master\Location::factory(),
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'image_url' => 'units/sample.jpg',
        ];
    }
}
