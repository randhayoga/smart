<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $departments = \App\Models\Department::factory(5)->create();

        // foreach ($departments as $department) {
        //     $users = \App\Models\User::factory(10)->create([
        //         'department_id' => $department->id,
        //     ]);

        //     // Assign a manager to the department
        //     $department->update([
        //         'manager_id' => $users->first()->id,
        //     ]);
        // }

        // Create the hardcoded Admin user
        \App\Models\User::factory()->create([
            'username' => '255476',
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'department_id' => $departments->first()->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // Create the hardcoded Regular user
        \App\Models\User::factory()->create([
            'username' => '123456',
            'name' => 'User',
            'email' => 'user@example.com',
            'role' => 'user',
            'department_id' => $departments->first()->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
    }
}
