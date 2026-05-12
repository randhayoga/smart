<?php

namespace Database\Factories\Master;

use App\Models\Master\Organizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Organizer>
 */
class OrganizerFactory extends Factory
{
    protected $model = Organizer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper(substr(fake()->unique()->name(), 0, 3)),
        ];
    }
}
