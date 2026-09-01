<?php

namespace App\Services\Mercure;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

/**
 * Mercure JWT Service generating and signing RFC 7519 compliant JSON Web Tokens for Mercure Hub authorization.
 */
class MercureJwtService
{
    /**
     * Generate a dynamic JWT for subscribing to Mercure topics.
     *
     * @param array<int, string>|string $subscribeTopics Topics the client is authorized to receive (can be wildcards or exact URIs)
     * @param array<string, mixed> $payload Additional private claims to embed into the token
     * @param int|null $lifetimeSeconds Token lifetime in seconds (defaults to config)
     * @return string Signed JWT string
     */
    public function generateSubscriberToken(
        array|string $subscribeTopics = [],
        array $payload = [],
        ?int $lifetimeSeconds = null
    ): string {
        $topics = is_array($subscribeTopics) ? $subscribeTopics : [$subscribeTopics];
        $lifetime = $lifetimeSeconds ?? (int) config('mercure.subscriber_token_lifetime', 3600);
        $secret = (string) config('mercure.subscriber_key', config('mercure.jwt_secret'));

        return $this->createToken(
            mercureClaim: [
                'subscribe' => array_values($topics),
                'payload' => $payload,
            ],
            secretKey: $secret,
            lifetimeSeconds: $lifetime
        );
    }

    /**
     * Generate a dynamic JWT for a specific user to subscribe to their private notification topic.
     *
     * @param int|string $userId The user ID
     * @param int|null $lifetimeSeconds Token lifetime in seconds (defaults to config)
     * @return string Signed JWT string
     */
    public function generateUserSubscriberToken(
        int|string $userId,
        ?int $lifetimeSeconds = null
    ): string {
        $topic = rtrim(config('app.url', 'http://localhost'), '/') . "/notifications/users/{$userId}";

        return $this->generateSubscriberToken([$topic], [], $lifetimeSeconds);
    }

    /**
     * Generate a dynamic JWT for publishing to Mercure topics.
     *
     * @param array<int, string>|string $publishTopics Topics the publisher is authorized to publish to (defaults to ['*'])
     * @param int|null $lifetimeSeconds Token lifetime in seconds (defaults to 300s)
     * @return string Signed JWT string
     */
    public function generatePublisherToken(
        array|string $publishTopics = ['*'],
        ?int $lifetimeSeconds = null
    ): string {
        $topics = is_array($publishTopics) ? $publishTopics : [$publishTopics];
        $lifetime = $lifetimeSeconds ?? (int) config('mercure.publisher_token_lifetime', 300);
        $secret = (string) config('mercure.publisher_key', config('mercure.jwt_secret'));

        return $this->createToken(
            mercureClaim: [
                'publish' => array_values($topics),
            ],
            secretKey: $secret,
            lifetimeSeconds: $lifetime
        );
    }

    /**
     * Build and sign the JWT according to RFC 7519 and Mercure specifications.
     *
     * @param array<string, mixed> $mercureClaim The 'mercure' claim structure
     * @param string $secretKey The HMAC secret key
     * @param int $lifetimeSeconds Lifetime of token
     * @return string
     */
    protected function createToken(
        array $mercureClaim,
        string $secretKey,
        int $lifetimeSeconds
    ): string {
        $jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secretKey)
        );

        $now = new DateTimeImmutable();
        $expiresAt = $now->modify("+{$lifetimeSeconds} seconds");

        $builder = $jwtConfig->builder()
            ->issuedBy(config('app.url', 'http://localhost'))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expiresAt)
            ->withClaim('mercure', $mercureClaim);

        return $builder->getToken($jwtConfig->signer(), $jwtConfig->signingKey())->toString();
    }
}
