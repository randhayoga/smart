<?php

namespace App\Http\Controllers\Smart;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification Controller managing in-app notifications and real-time Mercure subscription tokens.
 */
class NotificationController extends Controller
{
    /**
     * Get list of notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unread = $user->unreadNotifications()->limit(50)->get();
        $remaining = max(0, 15 - $unread->count());
        $read = $remaining > 0
            ? $user->readNotifications()->limit($remaining)->get()
            : collect();

        $notifications = $unread->merge($read)
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'message' => $n->data['message'] ?? '',
                    'type' => $n->data['type'] ?? 'info',
                    'url' => $n->data['url'] ?? null,
                    'read' => $n->read_at !== null,
                    'timestamp' => $n->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark single notification as unread.
     */
    public function markAsUnread(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsUnread();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications for authenticated user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Delete all read notifications for authenticated user.
     */
    public function clearRead(Request $request): JsonResponse
    {
        $request->user()->readNotifications()->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get real-time Mercure subscription credentials for authenticated user.
     */
    public function token(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $publisher = app(\App\Services\Mercure\MercurePublisher::class);
        $jwtService = app(\App\Services\Mercure\MercureJwtService::class);

        return response()->json([
            'hubUrl' => config('mercure.public_url'),
            'topic' => $publisher->getUserTopic($userId),
            'token' => $jwtService->generateUserSubscriberToken($userId),
        ]);
    }
}
