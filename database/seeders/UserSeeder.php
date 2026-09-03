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
            'employee_id' => '252525',
            'employee_name' => 'Mas Mas Aset',
            'email' => 'admin@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '252525',
            'name' => 'Mas Mas Aset',
            'password_hash' => Hash::make('IfScFS?25#*'),
        ]);

        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '121212',
            'employee_name' => 'Karyawan Teladan',
            'email' => 'user@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '121212',
            'name' => 'Karyawan Teladan',
            'password_hash' => Hash::make('IfSIcT?25*#!'),
        ]);

        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '010101',
            'employee_name' => 'Dep Manajer',
            'email' => 'manager@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '010101',
            'name' => 'Dep Manajer',
            'password_hash' => Hash::make('IfSerVicEs?25#!*'),
        ]);

        HrdEmployee::create([
            'orgchart_id' => $org->id,
            'employee_id' => '090909',
            'employee_name' => 'Proyek Manajer',
            'email' => 'jaferid895@slotbeer.com',
            'active' => true,
        ]);
        AdmUser::create([
            'employee_id' => '090909',
            'name' => 'Proyek Manajer',
            'password_hash' => Hash::make('IfSPM?25#!*'),
        ]);

        // Set the manager in HRD_ORGCHART
        $org->update(['employee_id' => '010101']);
    }
}
