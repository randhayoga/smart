<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated Session Controller managing login, authentication redirects, and logout lifecycle.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $redirect = $request->input('redirect')
            ?: $request->query('redirect')
            ?: $request->session()->pull('url.intended');

        $request->session()->regenerate();

        if ($redirect) {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost();
            $targetHost = parse_url($redirect, PHP_URL_HOST);

            $isInternal = (str_starts_with($redirect, '/') && !str_starts_with($redirect, '//'))
                || ($targetHost === null || $targetHost === $request->getHost() || $targetHost === $appHost);

            if ($isInternal && !str_contains($redirect, '/auth/login') && $redirect !== route('login') && $redirect !== url('/')) {
                return redirect()->to($redirect);
            }
        }

        $user = Auth::user();
        if ($user && $user->is_admin) {
            return redirect()->route('smart.dashboard');
        }

        return redirect()->route('smart.user.dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
