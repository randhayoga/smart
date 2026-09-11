<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller handling user application locale selection and persistence.
 * Adheres to CruddyByDesign by treating locale preference as a resource with an update operation.
 */
class LocaleController extends Controller
{
    /**
     * Supported application locales.
     *
     * @var array<int, string>
     */
    public const SUPPORTED_LOCALES = ['id', 'en'];

    /**
     * Update the active application locale.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(self::SUPPORTED_LOCALES)],
        ]);

        $locale = $validated['locale'];
        $request->session()->put('locale', $locale);

        // Also persist in cookie (1 year) for guests or across sessions
        cookie()->queue(cookie()->make('app_locale', $locale, 60 * 24 * 365, null, null, false, false));

        return back();
    }
}
