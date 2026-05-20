<?php

namespace Database\Factories\Master;

use App\Models\Master\Floor;
use App\Models\Master\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Floor>
 */
class FloorFactory extends Factory
{
    protected $model = Floor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Lantai ' . fake()->numberBetween(1, 10),
            'location_id' => Location::factory(),
        ];
    }
}
