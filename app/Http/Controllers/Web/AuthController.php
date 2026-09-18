<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Controller;
use App\Services\Auth\VerifySession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Handle incoming SSO login request from the portal.
     */
    public function login(Request $request, VerifySession $verifyService): mixed
    {
        // If SSO is disabled (development mode), delegate to standard dev login
        if (!config('auth.portal_sso.enabled', false)) {
            if (Auth::check()) {
                return redirect()->route('smart.dashboard');
            }
            return app(AuthenticatedSessionController::class)->create();
        }

        $ciSession = $request->query('ciSession');

        // If no session passed, redirect unauthenticated user to Central Portal
        if (empty($ciSession)) {
            if (Auth::check()) {
                $user = Auth::user();
                if (!$user->is_admin) {
                    return response()->view('errors.phase1', [
                        'portalUrl' => config('app.redirect.portal', 'https://portal.ptre.co.id'),
                    ], 403);
                }
                return redirect()->route('smart.dashboard');
            }

            $portalUrl = config('app.redirect.portal', 'https://portal.ptre.co.id');
            if ($request->inertia()) {
                return Inertia::location($portalUrl);
            }
            return redirect()->away($portalUrl);
        }

        // Clear any existing local session to ensure clean adopt of incoming SSO identity
        if (Auth::check()) {
            Auth::logout();
        }

        $user = $verifyService->checkAndGetUserByCiSession($ciSession);

        if ($user) {
            // Phase 1 Access Control:
            // If user is not an Admin or IFS Manager, deny access with dedicated Phase 1 error page
            if (!$user->is_admin) {
                return response()->view('errors.phase1', [
                    'portalUrl' => config('app.redirect.portal', 'https://portal.ptre.co.id'),
                ], 403);
            }

            // Log in verified user
            Auth::login($user);
            $request->session()->regenerate();

            // Validate redirect URL to prevent open redirect vulnerabilities
            $redirect = $request->input('redirect')
                ?: $request->query('redirect')
                ?: $request->session()->pull('url.intended');

            if ($redirect) {
                $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost();
                $targetHost = parse_url($redirect, PHP_URL_HOST);

                $isInternal = (str_starts_with($redirect, '/') && !str_starts_with($redirect, '//'))
                    || ($targetHost === null || $targetHost === $request->getHost() || $targetHost === $appHost);

                if ($isInternal && !str_contains($redirect, '/login') && $redirect !== route('login') && $redirect !== url('/')) {
                    return redirect()->to($redirect);
                }
            }

            return redirect()->route('smart.dashboard');
        }

        // Session not found, expired, or employee record not found
        abort(401, 'Unauthorized SSO Session.');
    }

    /**
     * Logout and redirect to portal.
     */
    public function logout(Request $request): Response
    {
        // If SSO is disabled (development mode), delegate to standard dev logout
        if (!config('auth.portal_sso.enabled', false)) {
            return app(AuthenticatedSessionController::class)->destroy($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $portalUrl = config('app.redirect.portal', 'https://portal.ptre.co.id');
        if ($request->inertia()) {
            return Inertia::location($portalUrl);
        }

        return redirect()->away($portalUrl);
    }
}
