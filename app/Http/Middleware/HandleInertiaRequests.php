<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Handle Inertia Requests Middleware sharing global authentication, notifications, real-time Mercure tokens, and flash messages.
 */
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
        $user = $request->user();

        // Compute request sidebar badge counts in at most 1 combined SQL query per request
        $requestBadgeCounts = null;
        $getRequestBadgeCounts = function () use ($user, &$requestBadgeCounts): array {
            if ($requestBadgeCounts !== null) {
                return $requestBadgeCounts;
            }
            if (!$user) {
                return $requestBadgeCounts = ['pendingRequest' => 0, 'pendingAdminApproved' => 0, 'activeRequests' => 0];
            }

            $canApprove = $user->hasPermission('requests.approve');
            $canConfirm = $user->hasPermission('requests.confirm');
            $canViewInbox = $user->hasPermission('requests.inbox.view');

            if (!$canApprove && !$canConfirm && !$canViewInbox) {
                return $requestBadgeCounts = ['pendingRequest' => 0, 'pendingAdminApproved' => 0, 'activeRequests' => 0];
            }

            $row = \Illuminate\Support\Facades\DB::table('requests')
                ->selectRaw("
                    SUM(CASE WHEN approver_id = ? AND status = 'wait' THEN 1 ELSE 0 END) as pending_request_count,
                    SUM(CASE WHEN status = 'approve' THEN 1 ELSE 0 END) as pending_admin_approved_count,
                    SUM(CASE WHEN status IN ('approve', 'confirm', 'partial', 'handover', 'return')
                              OR status LIKE '%menunggu_serah_terima%'
                              OR status LIKE '%partial%' THEN 1 ELSE 0 END) as active_requests_count
                ", [(int) $user->id])
                ->first();

            return $requestBadgeCounts = [
                'pendingRequest' => $canApprove ? (int) ($row->pending_request_count ?? 0) : 0,
                'pendingAdminApproved' => $canConfirm ? (int) ($row->pending_admin_approved_count ?? 0) : 0,
                'activeRequests' => $canViewInbox ? (int) ($row->active_requests_count ?? 0) : 0,
            ];
        };

        $cachedUnreadNotifications = null;
        $getUnreadNotifications = function () use ($user, &$cachedUnreadNotifications) {
            if ($cachedUnreadNotifications === null) {
                $cachedUnreadNotifications = $user ? $user->unreadNotifications()->limit(51)->get() : collect();
            }
            return $cachedUnreadNotifications;
        };

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user ? $user->getRoleNames() : [],
                'permissions' => fn () => $user ? $user->getAllPermissionNames() : [],
                'pendingRequestCount' => fn () => $getRequestBadgeCounts()['pendingRequest'],
                'pendingAssetStatusCount' => fn () => $user && $user->hasPermission('inventory.status_approval.decide')
                    ? \App\Models\Inventory\AssetDeletionApproval::where('decision', 'pending')
                        ->whereHas('asset', fn ($q) => $q->where('approval_status', 'Pending:DM'))
                        ->count()
                    : 0,
                'pendingAssetActivationCount' => fn () => $user && $user->hasPermission('inventory.status_approval.decide')
                    ? \App\Models\Inventory\UnitActivationApproval::where('decision', 'pending')->count()
                    : 0,
                'pendingAdminApprovedCount' => fn () => $getRequestBadgeCounts()['pendingAdminApproved'],
                'activeRequestsCount' => fn () => $getRequestBadgeCounts()['activeRequests'],
                'notifications' => function () use ($user, $getUnreadNotifications) {
                    if (!$user) {
                        return [];
                    }

                    $unread = $getUnreadNotifications()->take(50);
                    $remaining = max(0, 15 - $unread->count());
                    $read = $remaining > 0
                        ? $user->readNotifications()->limit($remaining)->get()
                        : collect();

                    return $unread->merge($read)
                        ->sortByDesc('created_at')
                        ->values()
                        ->map(function ($n) {
                            $payload = \App\Services\NotificationService::formatNotificationPayload($n->data ?? []);

                            return [
                                'id' => $n->id,
                                'title' => $payload['title'] ?? '',
                                'message' => $payload['message'] ?? '',
                                'type' => $payload['type'] ?? 'info',
                                'url' => $payload['url'] ?? null,
                                'read' => $n->read_at !== null,
                                'timestamp' => $n->created_at->toIso8601String(),
                            ];
                        });
                },
                'unreadNotificationCount' => function () use ($user, $getUnreadNotifications) {
                    if (!$user) {
                        return 0;
                    }
                    $unread = $getUnreadNotifications();
                    return $unread->count() <= 50
                        ? $unread->count()
                        : $user->unreadNotifications()->count();
                },
                'mercure' => fn () => $request->user() ? [
                    'hubUrl' => config('mercure.public_url'),
                    'topic' => app(\App\Services\Mercure\MercurePublisher::class)->getUserTopic($request->user()->id),
                    'token' => tap(
                        $request->session()->has('mercure_token') && ($request->session()->get('mercure_token_expires_at', 0) > now()->timestamp)
                            ? $request->session()->get('mercure_token')
                            : tap(
                                app(\App\Services\Mercure\MercureJwtService::class)->generateUserSubscriberToken($request->user()->id),
                                function ($token) use ($request) {
                                    $request->session()->put('mercure_token', $token);
                                    // Cache for 50 minutes (token lifetime is 60 minutes)
                                    $request->session()->put('mercure_token_expires_at', now()->addMinutes(50)->timestamp);
                                }
                            ),
                        function ($token) use ($request) {
                            \Illuminate\Support\Facades\Cookie::queue(
                                (string) config('mercure.cookie_name', 'mercure_access_token'),
                                $token,
                                60,
                                '/.well-known/mercure',
                                null,
                                $request->isSecure(),
                                false,
                                false,
                                'lax'
                            );
                        }
                    ),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'new_stock_created' => fn () => $request->session()->get('new_stock_created'),
                'created_request' => fn () => $request->session()->get('created_request'),
            ],
            'locale' => fn () => app()->getLocale(),
            'supportedLocales' => [
                'id' => ['name' => 'Bahasa Indonesia', 'short' => 'ID', 'flag' => '🇮🇩'],
                'en' => ['name' => 'English', 'short' => 'EN', 'flag' => '🇬🇧'],
            ],
        ];
    }
}
