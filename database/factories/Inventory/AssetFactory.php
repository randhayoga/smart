<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Asset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'AST-' . fake()->unique()->numerify('#####'),
            'lot_id' => Lot::factory(),
            'location_id' => \App\Models\Master\Location::factory(),
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'image_url' => 'assets/sample.jpg',
            'burden' => 'Corporate',
            'project_id' => null,
            'vendor_id' => null,
            'specification' => null,
        ];
    }

    /**
     * Indicate that the asset is available and in good condition.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);
    }

    /**
     * Indicate that the asset has computer specifications.
     */
    public function comp(): static
    {
        return $this->state(fn (array $attributes) => [
            'specification' => 'Intel Core i7-13700H, 16GB RAM, 512GB SSD',
        ]);
    }
}
