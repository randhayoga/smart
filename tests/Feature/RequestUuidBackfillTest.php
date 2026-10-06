<?php

namespace Tests\Feature;

use App\Models\Request\Request as SmartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestUuidBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fill_missing_uuids_and_allow_nullable_utilization(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();

        // 1. Direct DB insert simulating data migration without UUID and with null utilization
        DB::table('requests')->insert([
            'request_number' => 'REQ-MIG-001',
            'user_id' => $user->id,
            'approver_id' => $approver->id,
            'utilization' => null,
            'reasoning' => 'Data migration from old system',
            'status' => 'completed',
            'uuid' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('requests')->insert([
            'request_number' => 'REQ-MIG-002',
            'user_id' => $user->id,
            'approver_id' => $approver->id,
            'utilization' => null,
            'reasoning' => 'Another migrated request',
            'status' => 'completed',
            'uuid' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(2, SmartRequest::whereNull('uuid')->count());

        // 2. Call fillMissingUuids
        $updated = SmartRequest::fillMissingUuids();
        $this->assertEquals(2, $updated);

        // 3. Verify all records now have valid UUIDs
        $this->assertEquals(0, SmartRequest::whereNull('uuid')->count());

        $req1 = SmartRequest::where('request_number', 'REQ-MIG-001')->first();
        $this->assertNotNull($req1->uuid);
        $this->assertTrue(Str::isUuid($req1->uuid));
        $this->assertNull($req1->utilization);

        // 4. Test Artisan command idempotency
        $this->artisan('requests:fill-missing-uuids')
            ->expectsOutputToContain('Successfully updated 0 request record(s)')
            ->assertSuccessful();
    }
}
