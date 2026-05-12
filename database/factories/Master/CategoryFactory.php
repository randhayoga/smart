<?php

namespace Database\Factories\Master;

use App\Models\Master\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());
        return [
            'code' => strtoupper(substr($name, 0, 3)),
            'name' => $name,
        ];
    }
}
