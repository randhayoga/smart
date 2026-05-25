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
        // Define the department name here
        $departmentName = 'IFS';
        $department = \App\Models\Department::factory()->create([
            'name' => $departmentName,
        ]);

        // Create the hardcoded Admin user
        \App\Models\User::factory()->create([
            'username' => '255578',
            'name' => 'Radifa',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'department_id' => $department->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // Create the hardcoded Regular user
        \App\Models\User::factory()->create([
            'username' => '123456',
            'name' => 'Arya Gepa',
            'email' => 'user@example.com',
            'role' => 'user',
            'department_id' => $department->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // Create the hardcoded Manager user
        $manager = \App\Models\User::factory()->create([
            'username' => '654321',
            'name' => 'Sonny Handini',
            'email' => 'manager@example.com',
            'role' => 'manager',
            'department_id' => $department->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $department->update(['manager_id' => $manager->id]);

        // Create projects
        $projects = [
            ['code' => 'PRJ-001', 'name' => 'PRJ-001 – Website Revamp'],
            ['code' => 'PRJ-002', 'name' => 'PRJ-002 – Mobile App'],
            ['code' => 'PRJ-003', 'name' => 'PRJ-003 – ERP Integration'],
        ];

        foreach ($projects as $proj) {
            \App\Models\Project::updateOrCreate(
                ['code' => $proj['code']],
                [
                    'name' => $proj['name'],
                    'manager_id' => $manager->id,
                ]
            );
        }
    }
}
