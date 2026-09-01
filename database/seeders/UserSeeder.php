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
        $org = HrdOrgchart::create([
            'org_name' => 'Integrated Facility Services Department',
            'org_code' => 'IFS',
            'employee_id' => null,
        ]);

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
            'password_hash' => Hash::make('IfScFS?25#*'),
        ]);

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
            'password_hash' => Hash::make('IfSIcT?25*#!'),
        ]);

        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '654321',
            'employee_name' => 'Sonny Handini',
            'email' => 'sejoxor506@kolsea.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '654321',
            'name' => 'Sonny Handini',
            'password_hash' => Hash::make('IfSerVicEs?25#!*'),
        ]);

        // Set the manager in HRD_ORGCHART
        $org->update(['employee_id' => '654321']);
    }
}
