<?php

namespace Database\Seeders;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Deterministic fake usernames/employee IDs.
     */
    public const FAKE_USERNAMES = [
        '999998', // Admin: Mas Mas Aset
        '999997', // Regular User: Karyawan Teladan
        '999996', // Dept Manager: Dep Manajer
        '999995', // Project Manager: Proyek Manajer
    ];

    /**
     * Legacy fake IDs to purge if present from earlier tests/seeds.
     */
    public const LEGACY_FAKE_USERNAMES = [
        '252525',
        '121212',
        '010101',
        '090909',
    ];

    /**
     * Fake department codes.
     */
    public const FAKE_ORG_CODES = [
        'TEST-DEPT',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allFakeUsernames = array_values(array_unique(array_merge(self::FAKE_USERNAMES, self::LEGACY_FAKE_USERNAMES)));

        // 1. Safe cleanup: remove only fake records from external databases
        AdmUser::whereIn('username', $allFakeUsernames)->delete();
        HrdEmployee::whereIn('employee_id', $allFakeUsernames)->delete();
        HrdOrgchart::whereIn('org_code', self::FAKE_ORG_CODES)->delete();

        // 2. Create the dedicated fake test department in USER_HRIS
        $org = HrdOrgchart::create([
            'org_code' => 'TEST-DEPT',
            'org_name' => 'Departemen Fasilitas Uji Coba',
        ]);

        // 3. Seed fake employees in USER_HRIS and corresponding users in new_portal
        $adminEmp = HrdEmployee::create([
            'employee_id' => '999998',
            'orgchart_id' => $org->id,
            'employee_name' => 'Mas Mas Aset',
            'email' => 'admin@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'username' => '999998',
            'name' => 'Mas Mas Aset',
            'password' => Hash::make('IfScFS?25#*'),
        ]);

        HrdEmployee::create([
            'employee_id' => '999997',
            'orgchart_id' => $org->id,
            'employee_name' => 'Karyawan Teladan',
            'email' => 'user@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'username' => '999997',
            'name' => 'Karyawan Teladan',
            'password' => Hash::make('IfSIcT?25*#!'),
        ]);

        $depEmp = HrdEmployee::create([
            'employee_id' => '999996',
            'orgchart_id' => $org->id,
            'employee_name' => 'Dep Manajer',
            'email' => 'tamiyi7651@hebase.com',
            'active' => true,
        ]);
        AdmUser::create([
            'username' => '999996',
            'name' => 'Dep Manajer',
            'password' => Hash::make('IfSerVicEs?25#!*'),
        ]);

        $pmEmp = HrdEmployee::create([
            'employee_id' => '999995',
            'orgchart_id' => $org->id,
            'employee_name' => 'Proyek Manajer',
            'email' => 'pm@example.com',
            'active' => true,
        ]);
        AdmUser::create([
            'username' => '999995',
            'name' => 'Proyek Manajer',
            'password' => Hash::make('IfSPM?25#!*'),
        ]);

        // 4. Designate Dep Manajer (999996) as manager of TEST-DEPT
        $org->update(['employee_id' => $depEmp->id]);
    }
}
