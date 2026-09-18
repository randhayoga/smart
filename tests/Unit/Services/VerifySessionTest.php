<?php

namespace Tests\Unit\Services;

use App\Models\Portal\CISession;
use App\Models\User;
use App\Services\Auth\VerifySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerifySessionTest extends TestCase
{
    use RefreshDatabase;

    private VerifySession $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VerifySession();
    }

    public function test_check_and_get_user_by_ci_session_returns_user_for_valid_active_session(): void
    {
        $user = User::factory()->create([
            'employee_id' => '770001',
            'active' => true,
        ]);

        $sessionId = 'test_session_valid_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time(),
            'data' => 'uname|s:6:"770001";',
        ]);

        $result = $this->service->checkAndGetUserByCiSession($sessionId);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('770001', $result->employee_id);
    }

    public function test_check_and_get_user_by_ci_session_returns_false_for_non_existent_session(): void
    {
        $result = $this->service->checkAndGetUserByCiSession('non_existent_' . uniqid());

        $this->assertFalse($result);
    }

    public function test_check_and_get_user_by_ci_session_returns_false_for_expired_session(): void
    {
        $user = User::factory()->create([
            'employee_id' => '770002',
            'active' => true,
        ]);

        $sessionId = 'test_session_expired_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time() - (200 * 60), // older than 120 min
            'data' => 'uname|s:6:"770002";',
        ]);

        $result = $this->service->checkAndGetUserByCiSession($sessionId);

        $this->assertFalse($result);
    }

    public function test_check_and_get_user_by_ci_session_returns_false_when_uname_is_missing(): void
    {
        $sessionId = 'test_session_nouname_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time(),
            'data' => '__ci_last_regenerate|i:' . time() . ';',
        ]);

        $result = $this->service->checkAndGetUserByCiSession($sessionId);

        $this->assertFalse($result);
    }

    public function test_check_and_get_user_by_ci_session_returns_false_when_employee_is_inactive(): void
    {
        $user = User::factory()->create([
            'employee_id' => '770003',
            'active' => false,
        ]);

        $sessionId = 'test_session_inactive_' . uniqid();
        DB::connection('reportal')->table('ci_sessions')->insert([
            'id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'timestamp' => time(),
            'data' => 'uname|s:6:"770003";',
        ]);

        $result = $this->service->checkAndGetUserByCiSession($sessionId);

        $this->assertFalse($result);
    }

    public function test_parse_ci_session_decodes_serialized_string(): void
    {
        $data = 'uname|s:6:"255578";org_code|s:3:"IFS";';
        $decoded = $this->service->parseCiSession($data);

        $this->assertIsArray($decoded);
        $this->assertEquals('255578', $decoded['uname'] ?? null);
        $this->assertEquals('IFS', $decoded['org_code'] ?? null);
    }
}
