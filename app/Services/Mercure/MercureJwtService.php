<?php

namespace App\Services\Mercure;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

/**
 * Mercure JWT Service generating and signing RFC 9068 (OAuth 2.0 Access Token) and RFC 9396 (RAR authorization_details)
 * compliant JSON Web Tokens for Mercure 1.0 Hub authorization.
 */
class MercureJwtService
{
    public const AUTHORIZATION_DETAIL_TYPE = 'https://mercure.rocks/authorization-detail';

    /**
     * Generate a dynamic JWT for subscribing to Mercure topics.
     *
     * @param array<int, string>|string $subscribeTopics Topics the client is authorized to receive
     * @param array<string, mixed> $payload Additional private claims to embed into the authorization detail
     * @param int|null $lifetimeSeconds Token lifetime in seconds (defaults to config)
     * @param string|null $subject Optional token subject identifier (sub claim)
     * @return string Signed JWT string
     */
    public function generateSubscriberToken(
        array|string $subscribeTopics = [],
        array $payload = [],
        ?int $lifetimeSeconds = null,
        ?string $subject = null
    ): string {
        $topics = is_array($subscribeTopics) ? $subscribeTopics : [$subscribeTopics];
        $lifetime = $lifetimeSeconds ?? (int) config('mercure.subscriber_token_lifetime', 3600);
        $secret = (string) config('mercure.subscriber_key', config('mercure.jwt_secret'));

        $detail = [
            'type' => self::AUTHORIZATION_DETAIL_TYPE,
            'actions' => ['subscribe'],
            'topics' => array_map(
                static fn (string $topic): array => ['match' => $topic],
                array_values($topics)
            ),
        ];

        if (! empty($payload)) {
            $detail['payload'] = (object) $payload;
        }

        return $this->createToken(
            authorizationDetails: [$detail],
            secretKey: $secret,
            lifetimeSeconds: $lifetime,
            subject: $subject
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
        $topic = rtrim((string) config('app.url', 'http://localhost'), '/') . "/notifications/users/{$userId}";

        return $this->generateSubscriberToken([$topic], [], $lifetimeSeconds, (string) $userId);
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

        $detail = [
            'type' => self::AUTHORIZATION_DETAIL_TYPE,
            'actions' => ['publish'],
            'topics' => array_map(
                static fn (string $topic): array => ['match' => $topic],
                array_values($topics)
            ),
        ];

        return $this->createToken(
            authorizationDetails: [$detail],
            secretKey: $secret,
            lifetimeSeconds: $lifetime
        );
    }

    /**
     * Build and sign the JWT according to RFC 9068 and RFC 9396 Mercure 1.0 specifications.
     *
     * @param array<int, array<string, mixed>> $authorizationDetails The 'authorization_details' claim structure
     * @param string $secretKey The HMAC secret key
     * @param int $lifetimeSeconds Lifetime of token
     * @param string|null $subject Optional 'sub' claim
     * @return string
     */
    protected function createToken(
        array $authorizationDetails,
        string $secretKey,
        int $lifetimeSeconds,
        ?string $subject = null
    ): string {
        $jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secretKey)
        );

        $issuer = rtrim((string) config('mercure.issuer', config('app.url', 'http://localhost')), '/');
        $publicUrl = (string) config('mercure.public_url', 'http://localhost/.well-known/mercure');
        $audience = str_starts_with($publicUrl, 'http://') || str_starts_with($publicUrl, 'https://')
            ? $publicUrl
            : $issuer . '/' . ltrim($publicUrl, '/');

        $now = (new DateTimeImmutable())->setTimestamp(time());
        $expiresAt = $now->modify("+{$lifetimeSeconds} seconds");

        $builder = $jwtConfig->builder(ChainedFormatter::withUnixTimestampDates())
            ->withHeader('typ', 'at+jwt')
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->identifiedBy('urn:uuid:' . Str::uuid()->toString())
            ->relatedTo($subject ?? ('urn:uuid:' . Str::uuid()->toString()))
            ->withClaim('client_id', $issuer)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expiresAt)
            ->withClaim('authorization_details', $authorizationDetails);

        return $builder->getToken($jwtConfig->signer(), $jwtConfig->signingKey())->toString();
    }
}
