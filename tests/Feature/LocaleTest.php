<?php

namespace Tests\Feature;

use App\Http\Controllers\LocaleController;
use App\Models\User;
use App\Models\HrdEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Locale Feature Tests
 *
 * Verifies locale switching endpoint, validation, session and cookie persistence,
 * middleware locale application, and Inertia shared locale props.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_can_update_locale_to_english()
    {
        $response = $this->from(route('login'))->post(route('locale.update'), [
            'locale' => 'en',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('locale', 'en');
        $response->assertCookie('app_locale', 'en');
    }

    public function test_user_can_update_locale_to_indonesian_via_put()
    {
        $response = $this->from(route('login'))->put(route('locale.update'), [
            'locale' => 'id',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('locale', 'id');
        $response->assertCookie('app_locale', 'id');
    }

    public function test_invalid_locale_is_rejected()
    {
        $response = $this->from(route('login'))->post(route('locale.update'), [
            'locale' => 'fr',
        ]);

        $response->assertSessionHasErrors('locale');
        $this->assertNotEquals('fr', session('locale'));
    }

    public function test_middleware_sets_application_locale_from_session()
    {
        $this->withSession(['locale' => 'en'])->get(route('login'));

        $this->assertEquals('en', app()->getLocale());
    }

    public function test_middleware_sets_application_locale_from_cookie()
    {
        $this->withCookie('app_locale', 'en')->get(route('login'));

        $this->assertEquals('en', app()->getLocale());
    }

    public function test_inertia_shares_active_locale_and_supported_locales()
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('login'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->has('supportedLocales.id')
            ->has('supportedLocales.en')
        );
    }

    public function test_404_error_page_matches_selected_locale()
    {
        HrdEmployee::factory()->create(['employee_id' => '999998']);
        $user = User::factory()->create(['employee_id' => '999998']);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/smart/inventory/ATKHVS400011');

        $response->assertStatus(404);
        $response->assertSee(__('errors.404.title', [], 'en'));
        $response->assertSee(__('errors.404.badge', [], 'en'));
        $response->assertSee(__('errors.back', [], 'en'));
    }
}
