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
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'image_url' => 'units/sample.jpg',
            'burden' => 'Corporate',
            'project_id' => null,
            'vendor_id' => null,
            'specification' => null,
        ];
    }

    /**
     * Indicate that the unit is available and good condition.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);
    }

    /**
     * Indicate that the unit has computer specifications.
     */
    public function comp(): static
    {
        return $this->state(fn (array $attributes) => [
            'specification' => 'Intel Core i7-13700H, 16GB RAM, 512GB SSD',
        ]);
    }
}
