<?php

namespace Database\Factories;

use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\User;
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
        do {
            $employeeId = '88' . fake()->numerify('####');
        } while (User::where('employee_id', $employeeId)->exists());

        return [
            'orgchart_id' => HrdOrgchart::factory(),
            'employee_id' => $employeeId,
            'employee_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }

    public function create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        $employeeId = $attributes['employee_id'] ?? $attributes['username'] ?? null;
        if (!empty($employeeId)) {
            $existing = HrdEmployee::where('employee_id', (string) $employeeId)->first();
            if ($existing) {
                $existing->update($attributes);
                return $existing;
            }
        }

        return parent::create($attributes, $parent);
    }
}
