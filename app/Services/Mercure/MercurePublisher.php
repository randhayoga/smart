<?php

namespace App\Services\Mercure;

use App\Models\AdmUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mercure Publisher Service publishing real-time Server-Sent Events (SSE) to the Mercure Hub.
 */
class MercurePublisher
{
    /**
     * Create a new MercurePublisher instance.
     *
     * @param MercureJwtService $jwtService
     */
    public function __construct(
        protected MercureJwtService $jwtService
    ) {}

    /**
     * Publish an event to a user's private notification topic.
     *
     * @param AdmUser|int|string $user
     * @param array<string, mixed> $data
     * @return bool
     */
    public function publishToUser(AdmUser|int|string $user, array $data): bool
    {
        $userId = $user instanceof AdmUser ? $user->id : $user;
        $topic = $this->getUserTopic($userId);

        return $this->publish(
            topics: [$topic],
            data: $data,
            private: true
        );
    }

    /**
     * Batch publish an event to multiple users' private topics in a single request.
     *
     * @param iterable<AdmUser|int|string> $users
     * @param array<string, mixed> $data
     * @param bool $private
     * @return bool
     */
    public function publishToUsers(iterable $users, array $data, bool $private = true): bool
    {
        $topics = [];
        foreach ($users as $user) {
            $userId = $user instanceof AdmUser ? $user->id : $user;
            $topics[] = $this->getUserTopic($userId);
        }

        $uniqueTopics = array_values(array_unique(array_filter($topics)));
        if (empty($uniqueTopics)) {
            return true;
        }

        return $this->publish(
            topics: $uniqueTopics,
            data: $data,
            private: $private
        );
    }

    /**
     * Publish data to one or more Mercure topics in a single HTTP request.
     *
     * @param array<int, string>|string $topics
     * @param array<string, mixed> $data
     * @param bool $private Whether this update requires authorization to read
     * @return bool
     */
    public function publish(array|string $topics, array $data, bool $private = true): bool
    {
        $topics = is_array($topics) ? array_values(array_unique($topics)) : [$topics];
        if (empty($topics)) {
            return true;
        }

        $hubUrl = config('mercure.url');
        $jwt = $this->jwtService->generatePublisherToken($topics);

        try {
            // Build form parameters according to Mercure specification
            $params = [];
            foreach ($topics as $topic) {
                $params[] = 'topic=' . urlencode($topic);
            }
            $params[] = 'data=' . urlencode(json_encode($data));
            if ($private) {
                $params[] = 'private=on';
            }
            $body = implode('&', $params);

            $httpClient = Http::timeout(5);

            // In local/testing development, bypass self-signed SSL verification for localhost.
            // In production, enforce TLS certificate verification.
            if (!app()->isProduction()) {
                $httpClient = $httpClient->withoutVerifying();
            }

            $response = $httpClient->withToken($jwt)
                ->withBody($body, 'application/x-www-form-urlencoded')
                ->post($hubUrl);

            if (!$response->successful()) {
                Log::error('Mercure publish failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'topics' => $topics,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Mercure publish exception: ' . $e->getMessage(), [
                'exception' => $e,
                'topics' => $topics,
            ]);

            return false;
        }
    }

    /**
     * Get the standardized private topic URI for a user.
     */
    public function getUserTopic(int|string $userId): string
    {
        return rtrim(config('app.url', 'http://localhost'), '/') . "/notifications/users/{$userId}";
    }
}
