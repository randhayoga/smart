<?php

namespace Database\Factories;

use App\Models\AdmUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdmUser>
 */
class AdmUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AdmUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = User::factory()->create();

        return [
            'login_name' => $employee->employee_id,
            'name' => $employee->employee_name,
            'password' => md5('password'),
            'employee_id' => $employee->employee_id,
            'active' => true,
            'login_ldap' => 0,
            'flag_external' => 0,
        ];
    }
}
