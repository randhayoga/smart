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
                'user' => $request->user()?->loadMissing('hrdEmployee.orgchart'),
                'isAdmin' => $request->user()?->is_admin ?? false,
                'pendingRequestCount' => $request->user() && in_array($request->user()->role, ['manager', 'ifs_manager'])
                    ? \App\Models\Request\Request::where('approver_id', $request->user()->id)->where('status', 'wait')->count()
                    : 0,
                'pendingAssetStatusCount' => $request->user() && in_array($request->user()->role, ['manager', 'ifs_manager'])
                    ? \App\Models\Inventory\UnitStatusApproval::where('decision', 'pending')->whereHas('unit', fn($q) => $q->where('status', 'Pending:DM'))->count()
                    : 0,
                'notifications' => function () use ($request) {
                    if (! $request->user()) {
                        return [];
                    }

                    $user = $request->user();
                    $unread = $user->unreadNotifications()->limit(50)->get();
                    $remaining = max(0, 15 - $unread->count());
                    $read = $remaining > 0
                        ? $user->readNotifications()->limit($remaining)->get()
                        : collect();

                    return $unread->merge($read)
                        ->sortByDesc('created_at')
                        ->values()
                        ->map(fn ($n) => [
                            'id' => $n->id,
                            'title' => $n->data['title'] ?? '',
                            'message' => $n->data['message'] ?? '',
                            'type' => $n->data['type'] ?? 'info',
                            'url' => $n->data['url'] ?? null,
                            'read' => $n->read_at !== null,
                            'timestamp' => $n->created_at->toIso8601String(),
                        ]);
                },
                'unreadNotificationCount' => fn () => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
                'mercure' => fn () => $request->user() ? [
                    'hubUrl' => config('mercure.public_url'),
                    'topic' => app(\App\Services\Mercure\MercurePublisher::class)->getUserTopic($request->user()->id),
                    'token' => $request->session()->has('mercure_token') && ($request->session()->get('mercure_token_expires_at', 0) > now()->timestamp)
                        ? $request->session()->get('mercure_token')
                        : tap(
                            app(\App\Services\Mercure\MercureJwtService::class)->generateUserSubscriberToken($request->user()->id),
                            function ($token) use ($request) {
                                $request->session()->put('mercure_token', $token);
                                // Cache for 50 minutes (token lifetime is 60 minutes)
                                $request->session()->put('mercure_token_expires_at', now()->addMinutes(50)->timestamp);
                            }
                        ),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
