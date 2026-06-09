<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\HrdEmployee;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable the unit test admin bypass so we can test actual roles
        config(['app.disable_test_admin_bypass' => true]);
    }

    private function createManager(): AdmUser
    {
        $managerUser = AdmUser::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);
        return $managerUser;
    }

    private function createRequester(): AdmUser
    {
        return AdmUser::factory()->create();
    }

    public function test_only_manager_can_access_request_approval_pages(): void
    {
        $user = $this->createRequester();
        $manager = $this->createManager();

        // 1. Regular user gets 403
        $this->actingAs($user)->get(route('smart.approve'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.approved'))->assertStatus(403);

        // 2. Manager gets 200
        $this->actingAs($manager)->get(route('smart.approve'))->assertStatus(200);
        $this->actingAs($manager)->get(route('smart.approved'))->assertStatus(200);
    }

    public function test_manager_can_approve_request(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000001',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Need laptop',
            'status' => 'wait',
        ]);

        $response = $this->actingAs($manager)->post(route('smart.approve.action', $req->id), [
            'action' => 'approve',
            'note' => 'Approved request',
        ]);

        $response->assertRedirect(route('smart.approve'));
        $req->refresh();
        $this->assertEquals('approve', $req->status);
        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $req->id,
            'approver_id' => $manager->id,
            'decision' => 'approve',
            'note' => 'Approved request',
        ]);
        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'wait',
            'status_to' => 'approve',
            'changed_by' => $manager->id,
        ]);
    }

    public function test_manager_can_reject_request(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000002',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Need monitor',
            'status' => 'wait',
        ]);

        $response = $this->actingAs($manager)->post(route('smart.approve.action', $req->id), [
            'action' => 'reject',
            'note' => 'Not available',
        ]);

        $response->assertRedirect(route('smart.approve'));
        $req->refresh();
        $this->assertEquals('reject', $req->status);
        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $req->id,
            'approver_id' => $manager->id,
            'decision' => 'reject',
            'note' => 'Not available',
        ]);
    }

    public function test_manager_can_bulk_action_requests(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();

        $req1 = SmartRequest::create([
            'request_number' => 'REQ-0000003',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Reason 1',
            'status' => 'wait',
        ]);

        $req2 = SmartRequest::create([
            'request_number' => 'REQ-0000004',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Reason 2',
            'status' => 'wait',
        ]);

        // Bulk Approve
        $response = $this->actingAs($manager)->post(route('smart.approve.bulk-action'), [
            'ids' => [$req1->id, $req2->id],
            'action' => 'approve',
            'note' => 'Bulk approve notes',
        ]);

        $response->assertStatus(302);
        $req1->refresh();
        $req2->refresh();
        $this->assertEquals('approve', $req1->status);
        $this->assertEquals('approve', $req2->status);

        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $req1->id,
            'approver_id' => $manager->id,
            'decision' => 'approve',
            'note' => 'Bulk approve notes',
        ]);

        // Reset status to test bulk reject
        $req1->update(['status' => 'wait']);
        $req2->update(['status' => 'wait']);

        // Bulk Reject
        $response = $this->actingAs($manager)->post(route('smart.approve.bulk-action'), [
            'ids' => [$req1->id, $req2->id],
            'action' => 'reject',
            'note' => 'Bulk reject notes',
        ]);

        $response->assertStatus(302);
        $req1->refresh();
        $req2->refresh();
        $this->assertEquals('reject', $req1->status);
        $this->assertEquals('reject', $req2->status);
    }
}
