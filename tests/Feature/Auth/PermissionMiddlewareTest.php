<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for PermissionMiddleware and route-level authorization enforcement.
 */
class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_permission_protected_routes(): void
    {
        $response = $this->get(route('smart.inventory'));
        $response->assertRedirectContains(route('login'));
    }

    public function test_user_without_permission_receives_403_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // Regular user lacks inventory.manage, master.view, and access.manage
        $this->actingAs($user)->get(route('smart.inventory'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.master'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.access.index'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.dashboard'))->assertStatus(403);
    }

    public function test_user_with_assigned_role_and_permission_can_access_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Admin role has inventory.manage, master.view, dashboard.admin.view
        $this->actingAs($user)->get(route('smart.inventory'))->assertStatus(200);
        $this->actingAs($user)->get(route('smart.master'))->assertStatus(200);
        $this->actingAs($user)->get(route('smart.dashboard'))->assertStatus(200);

        // Admin role does NOT have access.manage (exclusive to superadmin)
        $this->actingAs($user)->get(route('smart.access.index'))->assertStatus(403);
    }

    public function test_superadmin_wildcard_bypasses_all_permission_checks(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)->get(route('smart.access.index'))->assertStatus(200);
        $this->actingAs($superadmin)->get(route('smart.inventory'))->assertStatus(200);
        $this->actingAs($superadmin)->get(route('smart.master'))->assertStatus(200);
        $this->actingAs($superadmin)->get(route('smart.dashboard'))->assertStatus(200);
    }

    public function test_multi_permission_or_logic(): void
    {
        // Route smart.scan-barcode accepts: permission:inventory.manage,inventory.borrow
        $roleWithBorrow = Role::create(['name' => 'borrower', 'label' => 'Borrower']);
        $borrowPerm = Permission::where('name', 'inventory.borrow')->firstOrFail();
        $roleWithBorrow->givePermissionTo($borrowPerm);

        $user = User::factory()->create();
        $user->assignRole($roleWithBorrow);

        // User with ONLY inventory.borrow satisfies OR condition
        $this->actingAs($user)->get(route('smart.scan-barcode'))->assertStatus(200);

        // User with neither permission gets 403
        $plainUser = User::factory()->create();
        $plainUser->assignRole('user');
        $this->actingAs($plainUser)->get(route('smart.scan-barcode'))->assertStatus(403);
    }

    public function test_custom_role_with_specific_permission_gains_access(): void
    {
        $auditorRole = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $auditPerm = Permission::where('name', 'audit.view')->firstOrFail();
        $auditorRole->givePermissionTo($auditPerm);

        $user = User::factory()->create();
        $user->assignRole($auditorRole);

        // Can access audit views
        $this->actingAs($user)->get(route('smart.audit'))->assertStatus(200);
        $this->actingAs($user)->get(route('smart.audit-stok'))->assertStatus(200);

        // Blocked on other views
        $this->actingAs($user)->get(route('smart.inventory'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.master'))->assertStatus(403);
    }

    public function test_permission_revocation_immediately_blocks_access(): void
    {
        $officerRole = Role::create(['name' => 'officer', 'label' => 'Officer']);
        $masterPerm = Permission::where('name', 'master.view')->firstOrFail();
        $officerRole->givePermissionTo($masterPerm);

        $user = User::factory()->create();
        $user->assignRole($officerRole);

        $this->actingAs($user)->get(route('smart.master'))->assertStatus(200);

        // Revoke permission from role
        $officerRole->revokePermissionTo($masterPerm);
        $user->refresh();

        $this->actingAs($user)->get(route('smart.master'))->assertStatus(403);
    }
}
