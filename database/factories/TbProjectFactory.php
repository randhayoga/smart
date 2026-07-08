<?php

namespace Database\Factories;

use App\Models\TbProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TbProject>
 */
class TbProjectFactory extends Factory
{
    protected $model = TbProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no_project' => fake()->unique()->numerify('##-####'),
            'project_name' => 'Project ' . fake()->words(1, true),
            'client_id' => (string)fake()->numberBetween(1, 10),
        ];
    }
}
