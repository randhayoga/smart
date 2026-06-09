<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => $request->user()?->is_admin ?? false,
                'pendingRequestCount' => $request->user() && in_array($request->user()->role, ['manager', 'ifs_manager'])
                    ? \App\Models\Request\Request::where('approver_id', $request->user()->id)->where('status', 'wait')->count()
                    : 0,
                'pendingAssetStatusCount' => $request->user() && in_array($request->user()->role, ['manager', 'ifs_manager'])
                    ? \App\Models\Inventory\UnitStatusApproval::where('decision', 'pending')->count()
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
