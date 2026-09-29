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
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Subcategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???-???')),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_consumable' => true,
            'category_id' => Category::factory(),
        ];
    }

    /**
     * Indicate that the subcategory is consumable.
     */
    public function consumable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_consumable' => true,
        ]);
    }

    /**
     * Indicate that the subcategory is an asset (non-consumable).
     */
    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_consumable' => false,
        ]);
    }
}
