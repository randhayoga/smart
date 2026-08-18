<?php

namespace App\Http\Controllers\Smart;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get list of notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->limit(50)
            ->get()
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
            'unreadCount' => $request->user()->unreadNotifications()->count(),
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
     * Delete all notifications for authenticated user.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

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
