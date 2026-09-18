<?php

namespace Tests\Feature\Auth;

use App\Models\HrdOrgchart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authentication Feature Tests
 *
 * Verifies local dev user login page rendering, valid credential login, invalid attempt rejections, and logout workflows.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.portal_sso.enabled' => false]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('smart.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_regular_users_and_non_ifs_managers_cannot_login_in_phase_1(): void
    {
        config(['app.disable_test_admin_bypass' => true]);

        $user = User::factory()->create(['employee_id' => '880011']);

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }

    public function test_ifs_manager_can_login_in_phase_1(): void
    {
        config(['app.disable_test_admin_bypass' => true]);

        $ifsUser = User::factory()->create(['employee_id' => '880022']);
        $orgchart = HrdOrgchart::create([
            'org_name' => 'Integrated Facilities Services',
            'org_code' => 'IFS',
            'employee_id' => $ifsUser->employee_id,
        ]);
        $ifsUser->update(['orgchart_id' => $orgchart->id]);

        $response = $this->post(route('login'), [
            'username' => $ifsUser->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('smart.dashboard', absolute: false));
    }

    public function test_user_can_authenticate_using_adm_user_md5_password(): void
    {
        $employeeId = '880033';
        $user = User::factory()->create([
            'employee_id' => $employeeId,
        ]);

        \App\Models\AdmUser::create([
            'login_name' => $employeeId,
            'name' => 'MD5 Test Employee',
            'password' => md5('MySuperSecretMD5!'),
            'employee_id' => $employeeId,
            'active' => 1,
        ]);

        $response = $this->post(route('login'), [
            'username' => $employeeId,
            'password' => 'MySuperSecretMD5!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('smart.dashboard', absolute: false));
    }

    public function test_user_cannot_authenticate_with_incorrect_adm_user_md5_password(): void
    {
        $employeeId = '880034';
        User::factory()->create([
            'employee_id' => $employeeId,
        ]);

        \App\Models\AdmUser::create([
            'login_name' => $employeeId,
            'name' => 'MD5 Test Employee 2',
            'password' => md5('MySuperSecretMD5!'),
            'employee_id' => $employeeId,
            'active' => 1,
        ]);

        $response = $this->post(route('login'), [
            'username' => $employeeId,
            'password' => 'InvalidMD5Attempt!',
        ]);

        $this->assertGuest();
    }
}

