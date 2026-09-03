<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Request History UUID Routing Feature Tests
 *
 * Verifies that request history index and detail routes strictly use UUIDs,
 * disallow legacy integer IDs with 404, automatically generate UUIDv7 upon model creation,
 * and enforce authorization checks per user.
 */
class RequestHistoryUuidRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    private function createUser(): AdmUser
    {
        return AdmUser::factory()->create();
    }

    public function test_new_request_automatically_generates_uuid(): void
    {
        $user = $this->createUser();
        $manager = $this->createUser('manager');

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000010',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test UUID creation',
            'status' => 'wait',
        ]);

        $this->assertNotNull($req->uuid);
        $this->assertTrue(Str::isUuid($req->uuid));
        $this->assertEquals($req->uuid, $req->getRouteKey());
    }

    public function test_user_can_view_request_detail_using_uuid(): void
    {
        $user = $this->createUser();
        $manager = $this->createUser('manager');

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000011',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test show detail',
            'status' => 'wait',
        ]);

        $response = $this->actingAs($user)->get(route('smart.history.show', $req));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/User/RequestHistoryDetail')
            ->has('request', fn (Assert $item) => $item
                ->where('uuid', $req->uuid)
                ->where('number', 'REQ-0000011')
                ->where('raw_status', 'wait')
                ->etc()
            )
        );
    }

    public function test_accessing_detail_via_raw_integer_id_returns_404(): void
    {
        $user = $this->createUser();
        $manager = $this->createUser('manager');

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000012',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test raw id failure',
            'status' => 'wait',
        ]);

        // Attempting to access using integer ID in URL
        $response = $this->actingAs($user)->get("/smart/history/{$req->id}");

        $response->assertStatus(404);
    }

    public function test_user_cannot_view_another_users_request_even_with_valid_uuid(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $manager = $this->createUser('manager');

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000013',
            'user_id' => $user1->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'User 1 private request',
            'status' => 'wait',
        ]);

        // User 2 attempts to view User 1's request via valid UUID
        $response = $this->actingAs($user2)->get(route('smart.history.show', $req));

        $response->assertStatus(404);
    }

    public function test_user_can_cancel_pending_request_using_uuid(): void
    {
        $user = $this->createUser();
        $manager = $this->createUser('manager');

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000014',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'To be cancelled',
            'status' => 'wait',
        ]);

        $response = $this->actingAs($user)->post(route('smart.history.cancel', $req), [
            'note' => 'Batal karena salah order',
        ]);

        $response->assertStatus(302);
        $req->refresh();
        $this->assertEquals('cancel', $req->status);
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'wait',
            'status_to' => 'cancel',
            'changed_by' => $user->id,
            'note' => 'Batal karena salah order',
        ]);
    }

    public function test_cancelling_request_with_raw_integer_id_returns_404(): void
    {
        $user = $this->createUser();
        $manager = $this->createUser('manager');

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000015',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test raw id cancel failure',
            'status' => 'wait',
        ]);

        $response = $this->actingAs($user)->post("/smart/history/{$req->id}/cancel", [
            'note' => 'Cancel with int ID',
        ]);

        $response->assertStatus(404);
        $req->refresh();
        $this->assertEquals('wait', $req->status);
    }
}
