<?php

namespace Database\Factories\Master;

use App\Models\Master\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'phone_number' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'description' => fake()->sentence(),
            'contact_person_1' => fake()->name(),
            'cp_email_1' => fake()->safeEmail(),
            'cp_phone_1' => fake()->phoneNumber(),
            'contact_person_2' => fake()->name(),
            'cp_email_2' => fake()->safeEmail(),
            'cp_phone_2' => fake()->phoneNumber(),
        ];
    }
}
