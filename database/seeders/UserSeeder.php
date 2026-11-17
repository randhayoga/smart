<?php

namespace Database\Seeders;

use App\Models\AdmUser;
use App\Models\User;
use App\Models\HrdOrgchart;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Deterministic fake usernames/employee IDs.
     */
    public const FAKE_USERNAMES = [
        '999997', // Regular User: Karyawan Teladan
        '999996', // TEST-DEPT / Acting IFS Manager (in local dev): Dep Manajer
        '999995', // Project Manager: Proyek Manajer
        '999994', // Organizer CFS: Organizer
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
        if (app()->isProduction()) {
            throw new \RuntimeException('UserSeeder is strictly prohibited in production to protect USER_HRIS.');
        }

        $allFakeUsernames = array_values(array_unique(array_merge(self::FAKE_USERNAMES, self::LEGACY_FAKE_USERNAMES)));

        // 1. Safe cleanup: remove fake records from USER_HRIS
        AdmUser::whereIn('login_name', $allFakeUsernames)
            ->orWhereIn('employee_id', $allFakeUsernames)
            ->delete();
        User::whereIn('employee_id', $allFakeUsernames)->delete();
        HrdOrgchart::whereIn('org_code', self::FAKE_ORG_CODES)->delete();

        // 2. Create the dedicated fake test department in USER_HRIS
        $org = HrdOrgchart::create([
            'org_code' => 'TEST-DEPT',
            'org_name' => 'Departemen Fasilitas Uji Coba',
        ]);

        // Resolve department of existing organizer_cfs users (fallback to TEST-DEPT in isolated test DBs)
        $organizerOrgchartId = User::whereIn('employee_id', User::ORGANIZER_CFS_EMPLOYEE_IDS)
            ->whereNotNull('orgchart_id')
            ->value('orgchart_id') ?? $org->id;

        // 3. Seed fake employees in USER_HRIS (hrd_employee) and credentials in USER_HRIS (adm_user)
        $users = [
            [
                'employee_id' => '999997',
                'name' => 'Karyawan Teladan',
                'email' => 'user@example.com',
                'password' => 'IfSIcT?25*#!',
                'orgchart_id' => $org->id,
            ],
            [
                'employee_id' => '999996',
                'name' => 'Dep Manajer',
                'email' => 'poxiho3795@iconcubic.com',
                'password' => 'IfSerVicEs?25#!*',
                'orgchart_id' => $org->id,
            ],
            [
                'employee_id' => '999995',
                'name' => 'Proyek Manajer',
                'email' => 'pm@example.com',
                'password' => 'IfSPM?25#!*',
                'orgchart_id' => $org->id,
            ],
            [
                'employee_id' => '999994',
                'name' => 'Organizer',
                'email' => 'organizer@example.com',
                'password' => 'IfSOrg?25#!*',
                'orgchart_id' => $organizerOrgchartId,
                'role' => 'organizer_cfs',
            ],
        ];

        $depEmp = null;
        foreach ($users as $userData) {
            $emp = User::create([
                'employee_id' => $userData['employee_id'],
                'orgchart_id' => $userData['orgchart_id'],
                'employee_name' => $userData['name'],
                'email' => $userData['email'],
                'active' => true,
            ]);

            AdmUser::create([
                'login_name' => $userData['employee_id'],
                'employee_id' => $userData['employee_id'],
                'name' => $userData['name'],
                'password' => md5($userData['password']),
                'active' => true,
            ]);

            if (!empty($userData['role']) && \App\Models\Auth\Role::where('name', $userData['role'])->exists()) {
                $emp->syncRoles([$userData['role']]);
            }

            if ($userData['employee_id'] === '999996') {
                $depEmp = $emp;
            }
        }

        // 4. Designate Dep Manajer (999996) as manager of TEST-DEPT
        if ($depEmp) {
            $org->update(['employee_id' => $depEmp->id]);
        }
    }
}
