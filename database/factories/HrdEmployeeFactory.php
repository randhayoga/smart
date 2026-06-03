<?php

namespace Database\Factories;

use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrdEmployeeFactory extends Factory
{
    protected $model = HrdEmployee::class;

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
