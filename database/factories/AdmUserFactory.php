<?php

namespace Database\Factories;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

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
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => function () {
                return HrdEmployee::factory()->create()->employee_id;
            },
            'name' => fake()->name(),
            'password_hash' => static::$password ??= Hash::make('password'),
        ];
    }
}
