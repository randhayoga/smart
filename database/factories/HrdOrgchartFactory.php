<?php

namespace Database\Factories;

use App\Models\HrdOrgchart;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrdOrgchartFactory extends Factory
{
    protected $model = HrdOrgchart::class;

    public function definition(): array
    {
        return [
            'org_name' => fake()->company() . ' Department',
            'org_code' => strtoupper(fake()->unique()->lexify('???')),
            'employee_id' => null,
        ];
    }
}
