<?php

namespace Database\Factories\Master;

use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Subcategory>
 */
class SubcategoryFactory extends Factory
{
    protected $model = Subcategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????-????')),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
        ];
    }
}
