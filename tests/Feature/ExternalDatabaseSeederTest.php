<?php

namespace Tests\Feature;

use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\TbRbs;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TbAssignProjectSeeder;
use Database\Seeders\TbProjectSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);

        // Ensure RBS codes exist for test environment
        TbRbs::firstOrCreate(['id' => 'P0'], ['name' => 'Anggota', 'showing_name' => 'Anggota']);
        TbRbs::firstOrCreate(['id' => 'P2211'], ['name' => 'Project Manager', 'showing_name' => 'Project Manager']);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_user_seeder_creates_fake_users_and_isolated_department(): void
    {
        // Pre-create an unrelated real-like department and user to verify they remain untouched
        $realDept = HrdOrgchart::create([
            'org_code' => 'REAL-DEPT',
            'org_name' => 'Real Department That Must Not Be Touched',
        ]);
        $realEmp = User::create([
            'employee_id' => '111111',
            'orgchart_id' => $realDept->id,
            'employee_name' => 'Real Employee',
            'email' => 'real@example.com',
            'active' => true,
        ]);

        $this->seed(UserSeeder::class);

        // Check test department
        $testDept = HrdOrgchart::where('org_code', 'TEST-DEPT')->first();
        $this->assertNotNull($testDept);

        // Real department and user must still exist untouched
        $this->assertDatabaseHas('hrd_orgchart', ['id' => $realDept->id], 'user_hris');
        $this->assertDatabaseHas('hrd_employee', ['employee_id' => '111111'], 'user_hris');

        // Verify all fake users
        foreach (UserSeeder::FAKE_USERNAMES as $username) {
            $this->assertDatabaseHas('hrd_employee', [
                'employee_id' => $username,
                'orgchart_id' => $testDept->id,
            ], 'user_hris');
        }

        // Verify roles
        $regular = User::where('employee_id', '999997')->first();
        $this->assertEquals('user', $regular->role);

        $deptManager = User::where('employee_id', '999996')->first();
        $this->assertEquals('manager', $deptManager->role);
    }

    public function test_tb_project_and_assign_seeders(): void
    {
        // Pre-create an unrelated project that must not be touched
        $realProject = TbProject::create([
            'no_project' => 'REAL-01',
            'project_name' => 'Real Existing Project',
            'client_id' => '10',
        ]);

        $this->seed(UserSeeder::class);
        $this->seed(TbProjectSeeder::class);
        $this->seed(TbAssignProjectSeeder::class);

        // Real project must still exist
        $this->assertDatabaseHas('tb_project', ['no_project' => 'REAL-01'], 'reportal');

        // Check fake projects
        foreach (TbProjectSeeder::FAKE_PROJECT_CODES as $code) {
            $this->assertDatabaseHas('tb_project', ['no_project' => $code], 'reportal');
        }

        // Check assignments
        $this->assertDatabaseHas('tb_assign_project', [
            'npk' => '999997',
            'no_project' => '99-9901',
        ], 'reportal');

        $this->assertDatabaseHas('tb_assign_project', [
            'npk' => '999995',
            'no_project' => '99-9901',
            'id_rbs' => 'P2211',
        ], 'reportal');

        // Project Manager 999995 should now evaluate to manager role
        $pm = User::where('employee_id', '999995')->first();
        $this->assertEquals('manager', $pm->role);
    }

    public function test_reseeding_is_idempotent_and_only_purges_fake_entries(): void
    {
        // Seed once
        $this->seed(UserSeeder::class);
        $this->seed(TbProjectSeeder::class);
        $this->seed(TbAssignProjectSeeder::class);

        // Pre-create an unrelated user and project
        $realDept = HrdOrgchart::create([
            'org_code' => 'PERS-DEPT',
            'org_name' => 'Persistent Department',
        ]);
        $realEmp = User::create([
            'employee_id' => '777777',
            'orgchart_id' => $realDept->id,
            'employee_name' => 'Persistent User',
            'email' => 'persist@example.com',
            'active' => true,
        ]);
        $realProject = TbProject::create([
            'no_project' => 'PERSIST-01',
            'project_name' => 'Persistent Project',
            'client_id' => '99',
        ]);

        // Re-seed all three seeders
        $this->seed(UserSeeder::class);
        $this->seed(TbProjectSeeder::class);
        $this->seed(TbAssignProjectSeeder::class);

        // Counts of fake entries must be exactly the expected number (no duplicates)
        $this->assertCount(count(UserSeeder::FAKE_USERNAMES), User::whereIn('employee_id', UserSeeder::FAKE_USERNAMES)->get());
        $this->assertCount(1, HrdOrgchart::whereIn('org_code', UserSeeder::FAKE_ORG_CODES)->get());
        $this->assertCount(2, TbProject::whereIn('no_project', TbProjectSeeder::FAKE_PROJECT_CODES)->get());
        $this->assertCount(2, TbAssignProject::whereIn('no_project', TbProjectSeeder::FAKE_PROJECT_CODES)->get());

        // Persistent records must remain intact
        $this->assertDatabaseHas('hrd_orgchart', ['id' => $realDept->id], 'user_hris');
        $this->assertDatabaseHas('hrd_employee', ['employee_id' => '777777'], 'user_hris');
        $this->assertDatabaseHas('tb_project', ['no_project' => 'PERSIST-01'], 'reportal');
    }

    public function test_seeders_are_strictly_prohibited_in_production(): void
    {
        $originalEnv = app()['env'];

        try {
            app()['env'] = 'production';

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Database seeding is strictly prohibited in production');
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        } finally {
            app()['env'] = $originalEnv;
        }
    }

    public function test_individual_external_seeders_are_strictly_prohibited_in_production(): void
    {
        $originalEnv = app()['env'];

        try {
            app()['env'] = 'production';

            // Test UserSeeder
            try {
                $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true]);
                $this->fail('UserSeeder should have thrown RuntimeException in production.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('UserSeeder is strictly prohibited in production', $e->getMessage());
            }

            // Test TbProjectSeeder
            try {
                $this->artisan('db:seed', ['--class' => TbProjectSeeder::class, '--force' => true]);
                $this->fail('TbProjectSeeder should have thrown RuntimeException in production.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('TbProjectSeeder is strictly prohibited in production', $e->getMessage());
            }

            // Test TbAssignProjectSeeder
            try {
                $this->artisan('db:seed', ['--class' => TbAssignProjectSeeder::class, '--force' => true]);
                $this->fail('TbAssignProjectSeeder should have thrown RuntimeException in production.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('TbAssignProjectSeeder is strictly prohibited in production', $e->getMessage());
            }
        } finally {
            app()['env'] = $originalEnv;
        }
    }
}
