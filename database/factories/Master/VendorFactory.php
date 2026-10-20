<?php

namespace Database\Factories\Master;

use App\Models\Master\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Vendor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'VN' . fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'address' => fake()->address(),
            'phone_number' => '08' . fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'description' => fake()->sentence(),
        ];
    }
}
