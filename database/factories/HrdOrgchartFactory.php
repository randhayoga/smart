<?php

namespace Database\Factories;

use App\Models\HrdOrgchart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HrdOrgchart>
 */
class HrdOrgchartFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = HrdOrgchart::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'org_name' => fake()->company() . ' Department',
            'org_code' => strtoupper(fake()->unique()->lexify('???')),
            'employee_id' => null,
        ];
    }
}
