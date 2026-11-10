<?php

namespace Tests\Unit;

use App\Services\Mercure\MercureJwtService;
use Laravel\Octane\Commands\StartFrankenPhpCommand;
use ReflectionMethod;
use Tests\TestCase;

class OctaneMercureConfigTest extends TestCase
{
    public function test_octane_mercure_config_uses_modern_issuer_block_without_compatibility_mode(): void
    {
        $mercure = config('octane.mercure');

        $this->assertIsArray($mercure);
        $this->assertArrayNotHasKey('protocol_version_compatibility', $mercure);
        $this->assertArrayNotHasKey('publisher_jwt', $mercure);
        $this->assertArrayNotHasKey('subscriber_jwt', $mercure);
        $this->assertArrayHasKey('resource_identifier', $mercure);
        $this->assertArrayHasKey('cookie_name', $mercure);
        $this->assertSame('mercure_access_token', $mercure['cookie_name']);
        $this->assertArrayHasKey('issuer', $mercure);
        $this->assertStringContainsString('publisher {', $mercure['issuer']);
        $this->assertStringContainsString('subscriber {', $mercure['issuer']);
        $this->assertTrue($mercure['subscriptions']);
    }

    public function test_octane_mercure_config_evaluation_with_secret(): void
    {
        $originalEnv = $_ENV['MERCURE_JWT_SECRET'] ?? null;
        $originalServer = $_SERVER['MERCURE_JWT_SECRET'] ?? null;

        $_ENV['MERCURE_JWT_SECRET'] = 'dummy_secret_for_test';
        $_SERVER['MERCURE_JWT_SECRET'] = 'dummy_secret_for_test';
        putenv('MERCURE_JWT_SECRET=dummy_secret_for_test');

        $config = require config_path('octane.php');

        $this->assertIsArray($config['mercure']);
        $this->assertArrayNotHasKey('protocol_version_compatibility', $config['mercure']);
        $this->assertSame('mercure_access_token', $config['mercure']['cookie_name']);
        $this->assertStringContainsString('jwt dummy_secret_for_test', $config['mercure']['issuer']);
        $this->assertTrue($config['mercure']['subscriptions']);

        if ($originalEnv !== null) {
            $_ENV['MERCURE_JWT_SECRET'] = $originalEnv;
            $_SERVER['MERCURE_JWT_SECRET'] = $originalServer;
            putenv("MERCURE_JWT_SECRET={$originalEnv}");
        } else {
            unset($_ENV['MERCURE_JWT_SECRET'], $_SERVER['MERCURE_JWT_SECRET']);
            putenv('MERCURE_JWT_SECRET');
        }
    }

    public function test_octane_frankenphp_command_builds_valid_modern_mercure_config(): void
    {
        $command = $this->app->make(StartFrankenPhpCommand::class);
        $method = new ReflectionMethod($command, 'buildMercureConfig');
        $method->setAccessible(true);

        $builtConfig = $method->invoke($command);

        $this->assertStringContainsString('mercure {', $builtConfig);
        $this->assertStringNotContainsString('protocol_version_compatibility', $builtConfig);
        $this->assertStringContainsString('resource_identifier ', $builtConfig);
        $this->assertStringContainsString('cookie_name mercure_access_token', $builtConfig);
        $this->assertStringContainsString('issuer ', $builtConfig);
        $this->assertStringContainsString('publisher {', $builtConfig);
        $this->assertStringContainsString('subscriber {', $builtConfig);
        $this->assertStringContainsString('subscriptions', $builtConfig);
    }

    public function test_mercure_jwt_service_mints_rfc9068_and_rfc9396_compliant_tokens(): void
    {
        $service = $this->app->make(MercureJwtService::class);

        $subToken = $service->generateUserSubscriberToken(42);
        [$headerB64, $payloadB64] = explode('.', $subToken);

        $header = json_decode(base64_decode(strtr($headerB64, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);

        $this->assertSame('at+jwt', $header['typ']);
        $this->assertSame('HS256', $header['alg']);
        $this->assertArrayNotHasKey('mercure', $payload);
        $this->assertIsInt($payload['iat']);
        $this->assertIsInt($payload['nbf']);
        $this->assertIsInt($payload['exp']);
        $this->assertSame('42', $payload['sub']);
        $this->assertSame(rtrim((string) config('app.url'), '/'), $payload['iss']);
        $this->assertSame($payload['iss'], $payload['client_id']);
        $this->assertStringStartsWith('urn:uuid:', $payload['jti']);
        $this->assertCount(1, $payload['authorization_details']);
        $this->assertSame(MercureJwtService::AUTHORIZATION_DETAIL_TYPE, $payload['authorization_details'][0]['type']);
        $this->assertSame(['subscribe'], $payload['authorization_details'][0]['actions']);
        $this->assertSame(
            [['match' => rtrim((string) config('app.url'), '/') . '/notifications/users/42']],
            $payload['authorization_details'][0]['topics']
        );

        $pubToken = $service->generatePublisherToken(['https://example.com/topic/1']);
        [, $pubPayloadB64] = explode('.', $pubToken);
        $pubPayload = json_decode(base64_decode(strtr($pubPayloadB64, '-_', '+/')), true);

        $this->assertSame(['publish'], $pubPayload['authorization_details'][0]['actions']);
        $this->assertSame([['match' => 'https://example.com/topic/1']], $pubPayload['authorization_details'][0]['topics']);
    }
}
