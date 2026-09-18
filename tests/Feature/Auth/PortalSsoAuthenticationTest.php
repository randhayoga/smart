<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalSsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'auth.portal_sso.enabled' => true,
            'app.redirect.portal' => 'https://portal.ptre.co.id',
            'auth.portal_sso.portal_url' => 'https://portal.ptre.co.id',
        ]);
    }

    public function test_unauthenticated_user_accessing_login_without_cisession_redirects_to_portal(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect('https://portal.ptre.co.id');
    }

    public function test_authenticated_admin_visiting_login_without_cisession_redirects_to_dashboard(): void
    {
        $admin = User::factory()->create(['employee_id' => '255578']);

        $response = $this->actingAs($admin)->get('/login');

        $response->assertRedirect(route('smart.dashboard'));
    }

    public function test_authenticated_non_admin_visiting_login_without_cisession_shows_phase1_error_page(): void
    {
        config(['app.disable_test_admin_bypass' => true]);
        $user = User::factory()->create(['employee_id' => '880099']);

        $response = $this->actingAs($user)->get('/login');

        $response->assertStatus(403);
        $response->assertSee('Application Not Available Yet');
        $response->assertSee('https://portal.ptre.co.id');
    }

    public function test_user_with_valid_cisession_and_admin_role_logs_in_and_redirects_to_dashboard(): void
    {
        $admin = User::firstWhere('employee_id', '255578') ?? User::factory()->create(['employee_id' => '255578']);

        $sessionId = 'sso_admin_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time(),
            'data' => 'uname|s:6:"255578";',
        ]);

        $response = $this->get('/login?ciSession=' . $sessionId);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('smart.dashboard'));
    }

    public function test_user_with_valid_cisession_and_intended_url_redirects_to_intended(): void
    {
        $admin = User::firstWhere('employee_id', '255578') ?? User::factory()->create(['employee_id' => '255578']);

        $sessionId = 'sso_intended_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time(),
            'data' => 'uname|s:6:"255578";',
        ]);

        $response = $this->withSession(['url.intended' => '/smart/admin/inventory'])
            ->get('/login?ciSession=' . $sessionId);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect('/smart/admin/inventory');
    }

    public function test_user_with_valid_cisession_but_non_admin_shows_phase1_error_page_with_403(): void
    {
        config(['app.disable_test_admin_bypass' => true]);
        $user = User::factory()->create(['employee_id' => '880055']);

        $sessionId = 'sso_nonadmin_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time(),
            'data' => 'uname|s:6:"880055";',
        ]);

        $response = $this->get('/login?ciSession=' . $sessionId);

        $response->assertStatus(403);
        $response->assertSee('Application Not Available Yet');
        $response->assertSee('Phase 1 - Limited Rollout');
        $response->assertSee('Return to Portal');
        $response->assertSee('https://portal.ptre.co.id');
        $this->assertGuest();
    }

    public function test_user_with_invalid_cisession_receives_401_unauthorized(): void
    {
        $response = $this->get('/login?ciSession=nonexistent_invalid_session');

        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_user_with_expired_cisession_receives_401_unauthorized(): void
    {
        $admin = User::firstWhere('employee_id', '255578') ?? User::factory()->create(['employee_id' => '255578']);

        $sessionId = 'sso_expired_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time() - (200 * 60),
            'data' => 'uname|s:6:"255578";',
        ]);

        $response = $this->get('/login?ciSession=' . $sessionId);

        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_logout_in_production_invalidates_session_and_redirects_to_portal(): void
    {
        $admin = User::firstWhere('employee_id', '255578') ?? User::factory()->create(['employee_id' => '255578']);

        $response = $this->actingAs($admin)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('https://portal.ptre.co.id');
    }

    public function test_logout_via_inertia_returns_409_location_redirect_to_portal(): void
    {
        $admin = User::firstWhere('employee_id', '255578') ?? User::factory()->create(['employee_id' => '255578']);

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/logout');

        $this->assertGuest();
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://portal.ptre.co.id');
    }
}
