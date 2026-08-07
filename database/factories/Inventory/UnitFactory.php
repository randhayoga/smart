<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

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
