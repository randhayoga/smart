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
        $this->command?->warn('Seeding skipped: AdmUser, HrdEmployee, and HrdOrgchart reside in external databases (new_portal, user_hris).');
        return;

        $org = HrdOrgchart::firstOrCreate(
            ['org_code' => 'IFS'],
            ['org_name' => 'Integrated Facility Services Department']
        );

        $adminEmp = HrdEmployee::updateOrCreate(
            ['employee_id' => '252525'],
            [
                'orgchart_id' => $org->id,
                'employee_name' => 'Mas Mas Aset',
                'email' => 'admin@example.com',
                'active' => true,
            ]
        );
        AdmUser::updateOrCreate(
            ['username' => '252525'],
            [
                'name' => 'Mas Mas Aset',
                'password' => Hash::make('IfScFS?25#*'),
            ]
        );

        HrdEmployee::updateOrCreate(
            ['employee_id' => '121212'],
            [
                'orgchart_id' => $org->id,
                'employee_name' => 'Karyawan Teladan',
                'email' => 'user@example.com',
                'active' => true,
            ]
        );
        AdmUser::updateOrCreate(
            ['username' => '121212'],
            [
                'name' => 'Karyawan Teladan',
                'password' => Hash::make('IfSIcT?25*#!'),
            ]
        );

        $depEmp = HrdEmployee::updateOrCreate(
            ['employee_id' => '010101'],
            [
                'orgchart_id' => $org->id,
                'employee_name' => 'Dep Manajer',
                'email' => 'tamiyi7651@hebase.com',
                'active' => true,
            ]
        );
        AdmUser::updateOrCreate(
            ['username' => '010101'],
            [
                'name' => 'Dep Manajer',
                'password' => Hash::make('IfSerVicEs?25#!*'),
            ]
        );

        HrdEmployee::updateOrCreate(
            ['employee_id' => '090909'],
            [
                'orgchart_id' => $org->id,
                'employee_name' => 'Proyek Manajer',
                'email' => 'pm@example.com',
                'active' => true,
            ]
        );
        AdmUser::updateOrCreate(
            ['username' => '090909'],
            [
                'name' => 'Proyek Manajer',
                'password' => Hash::make('IfSPM?25#!*'),
            ]
        );

        // Set the manager in HRD_ORGCHART if not already set
        if (!$org->employee_id) {
            $org->update(['employee_id' => $depEmp->id]);
        }
    }
}
