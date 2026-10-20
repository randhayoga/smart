<?php

namespace Database\Factories\Master;

use App\Models\Master\Vendor;
use App\Models\Master\VendorCp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master\VendorCp>
 */
class VendorCpFactory extends Factory
{
    protected $model = VendorCp::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'contact_person' => fake()->name(),
            'cp_email' => fake()->safeEmail(),
            'cp_phone' => '08' . fake()->numerify('##########'),
        ];
    }
}
