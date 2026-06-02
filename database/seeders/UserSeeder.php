<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HrdOrgchart;
use App\Models\HrdEmployee;
use App\Models\AdmUser;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create one HRD_ORGCHART
        $org = HrdOrgchart::create([
            'org_name' => 'Integrated Facility Services Department',
            'org_code' => 'IFS',
            'employee_id' => null,
        ]);

        // Create 255578 (Radifa)
        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '255578',
            'employee_name' => 'Radifa',
            'email' => 'admin@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '255578',
            'name' => 'Radifa',
            'password_hash' => Hash::make('password'),
        ]);

        // Create 123456 (Arya Gepa)
        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '123456',
            'employee_name' => 'Arya Gepa',
            'email' => 'user@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '123456',
            'name' => 'Arya Gepa',
            'password_hash' => Hash::make('password'),
        ]);

        // Create 654321 (Sonny Handini)
        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '654321',
            'employee_name' => 'Sonny Handini',
            'email' => 'manager@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '654321',
            'name' => 'Sonny Handini',
            'password_hash' => Hash::make('password'),
        ]);

        // Set the manager in HRD_ORGCHART
        $org->update(['employee_id' => '654321']);
    }
}
