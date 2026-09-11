<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LocaleController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware setting the application locale from session, persistent cookie, or application default.
 */
class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = LocaleController::SUPPORTED_LOCALES;
        $locale = $request->session()->get('locale', $request->cookie('app_locale', config('app.locale', 'id')));

        if (in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
