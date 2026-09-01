<?php

namespace Database\Factories;

use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HrdEmployee>
 */
class HrdEmployeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = HrdEmployee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orgchart_id' => HrdOrgchart::factory(),
            'employee_id' => (string)fake()->unique()->randomNumber(6, true),
            'employee_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }
}
