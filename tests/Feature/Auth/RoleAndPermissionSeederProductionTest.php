<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndPermissionSeederProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('FORCE_ROLE_SEED');
        parent::tearDown();
    }

    public function test_seeder_runs_successfully_in_production_on_first_execution(): void
    {
        $originalEnv = app()['env'];

        try {
            app()['env'] = 'production';

            $this->artisan('db:seed', [
                '--class' => RoleAndPermissionSeeder::class,
                '--force' => true,
            ])->assertSuccessful();

            $this->assertDatabaseHas('roles', ['name' => 'superadmin'], 'SMART');
            $this->assertDatabaseHas('roles', ['name' => 'admin'], 'SMART');
            $this->assertDatabaseHas('permissions', ['name' => 'access.manage'], 'SMART');

            $adminRole = Role::where('name', 'admin')->first();
            $this->assertTrue($adminRole->hasPermissionTo('inventory.view'));
            $this->assertFalse($adminRole->hasPermissionTo('access.manage'));
        } finally {
            app()['env'] = $originalEnv;
        }
    }

    public function test_seeder_safeguard_prevents_overwriting_custom_permissions_in_production(): void
    {
        $originalEnv = app()['env'];

        try {
            app()['env'] = 'production';

            // First run: establish base system roles & permissions
            $this->artisan('db:seed', [
                '--class' => RoleAndPermissionSeeder::class,
                '--force' => true,
            ])->assertSuccessful();

            $adminRole = Role::where('name', 'admin')->first();
            $this->assertTrue($adminRole->hasPermissionTo('inventory.view'));

            // Superadmin customizes admin permissions in the UI:
            // Revoke 'inventory.view' and grant 'access.manage' (custom change)
            $adminRole->revokePermissionTo('inventory.view');
            $adminRole->givePermissionTo('access.manage');

            $this->assertFalse($adminRole->hasPermissionTo('inventory.view'));
            $this->assertTrue($adminRole->hasPermissionTo('access.manage'));

            // Second run in production without FORCE_ROLE_SEED:
            $this->artisan('db:seed', [
                '--class' => RoleAndPermissionSeeder::class,
                '--force' => true,
            ])->assertSuccessful();

            // Refresh role relation from DB
            $adminRole->unsetRelation('permissions');

            // Customizations must be PRESERVED (safeguard skipped destructive sync)
            $this->assertFalse($adminRole->hasPermissionTo('inventory.view'), 'Revoked permission must remain revoked.');
            $this->assertTrue($adminRole->hasPermissionTo('access.manage'), 'Custom granted permission must remain granted.');
        } finally {
            app()['env'] = $originalEnv;
        }
    }

    public function test_force_override_re_syncs_permissions_to_factory_defaults_in_production(): void
    {
        $originalEnv = app()['env'];

        try {
            app()['env'] = 'production';

            // Initial setup
            $this->artisan('db:seed', [
                '--class' => RoleAndPermissionSeeder::class,
                '--force' => true,
            ])->assertSuccessful();

            $adminRole = Role::where('name', 'admin')->first();
            $adminRole->revokePermissionTo('inventory.view');
            $this->assertFalse($adminRole->hasPermissionTo('inventory.view'));

            // Set FORCE_ROLE_SEED environment variable
            putenv('FORCE_ROLE_SEED=true');

            // Reseed with force override
            $this->artisan('db:seed', [
                '--class' => RoleAndPermissionSeeder::class,
                '--force' => true,
            ])->assertSuccessful();

            // Permissions should be restored to defaults
            $adminRole->unsetRelation('permissions');
            $this->assertTrue($adminRole->hasPermissionTo('inventory.view'), 'Default permissions should be restored.');
            $this->assertFalse($adminRole->hasPermissionTo('access.manage'));
        } finally {
            putenv('FORCE_ROLE_SEED');
            app()['env'] = $originalEnv;
        }
    }

    public function test_seeder_runs_without_safeguard_in_testing_environment(): void
    {
        $this->assertEquals('testing', app()->environment());

        $this->artisan('db:seed', [
            '--class' => RoleAndPermissionSeeder::class,
        ])->assertSuccessful();

        $adminRole = Role::where('name', 'admin')->first();
        $adminRole->revokePermissionTo('inventory.view');

        // Re-seeding in testing environment should re-sync unconditionally
        $this->artisan('db:seed', [
            '--class' => RoleAndPermissionSeeder::class,
        ])->assertSuccessful();

        $adminRole->unsetRelation('permissions');
        $this->assertTrue($adminRole->hasPermissionTo('inventory.view'));
    }
}
