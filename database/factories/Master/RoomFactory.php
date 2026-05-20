<?php

namespace Database\Factories\Master;

use App\Models\Master\Room;
use App\Models\Master\Floor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ruang ' . fake()->numberBetween(101, 909),
            'floor_id' => Floor::factory(),
        ];
    }
}
