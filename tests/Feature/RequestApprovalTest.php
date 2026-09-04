<?php

namespace Tests\Feature;

use App\Mail\RequesterRequestRejectedMail;
use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\HrdEmployee;
use App\Models\Inventory\Barang;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Request Approval Feature Tests
 *
 * Verifies manager role access restrictions, approve/reject workflows,
 * in-app notifications and email dispatching to requesters.
 */
class RequestApprovalTest extends TestCase
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

    public function test_manager_can_approve_request_and_sends_in_app_notification(): void
    {
        Mail::fake();

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

        // Add a borrow item (non-consumable with start_date)
        RequestItem::create([
            'request_id' => $req->id,
            'quantity_requested' => 1,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ]);

        $response = $this->actingAs($manager)->post(route('smart.approve.bulk-action'), [
            'ids' => [$req->id],
            'action' => 'approve',
            'note' => 'Approved request',
        ]);

        $response->assertStatus(302);
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

        $admin = AdmUser::factory()->create(['employee_id' => '252525']);

        // Verify in-app notification sent to requester
        $notification = $requester->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('Peminjaman REQ-0000001 Disetujui', $notification->data['title']);
        $this->assertEquals("{$manager->name} menyetujui Peminjaman Anda dan sekarang sedang diproses oleh Tim Aset.", $notification->data['message']);
        $this->assertEquals('success', $notification->data['type']);
        $this->assertEquals("/smart/history/{$req->uuid}", $notification->data['url']);

        // Verify in-app notification sent to admin
        $adminNotification = $admin->notifications()->first();
        $this->assertNotNull($adminNotification);
        $this->assertEquals('Peminjaman: REQ-0000001 Di-approve', $adminNotification->data['title']);
        $this->assertEquals('Review jenis dan jumlah barang yang diminta pada halaman Inbox', $adminNotification->data['message']);
        $this->assertEquals('info', $adminNotification->data['type']);
        $this->assertEquals('/smart/inbox', $adminNotification->data['url']);

        // No rejection email should be dispatched
        Mail::assertNothingSent();
    }

    public function test_manager_can_reject_request_and_sends_notification_and_email(): void
    {
        Mail::fake();

        $manager = $this->createManager();
        $requester = $this->createRequester();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id, 'name' => 'Laptop']);
        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad T14',
            'specification' => 'Intel Core i7 16GB',
        ]);

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000002',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Need monitor and laptop',
            'status' => 'wait',
        ]);

        RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ]);

        $rejectionNote = 'Stok saat ini diprioritaskan untuk project mendesak.';

        $response = $this->actingAs($manager)->post(route('smart.approve.bulk-action'), [
            'ids' => [$req->id],
            'action' => 'reject',
            'note' => $rejectionNote,
        ]);

        $response->assertStatus(302);
        $req->refresh();
        $this->assertEquals('reject', $req->status);
        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $req->id,
            'approver_id' => $manager->id,
            'decision' => 'reject',
            'note' => $rejectionNote,
        ]);

        // Verify in-app notification
        $notification = $requester->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('Peminjaman REQ-0000002 Ditolak', $notification->data['title']);
        $this->assertEquals("{$manager->name} menolak Peminjaman Anda, alasan penolakan dapat dilihat pada detail Peminjaman.", $notification->data['message']);
        $this->assertEquals('error', $notification->data['type']);
        $this->assertEquals("/smart/history/{$req->uuid}", $notification->data['url']);

        // Verify email sent
        Mail::assertSent(RequesterRequestRejectedMail::class, function ($mail) use ($requester, $req, $manager, $rejectionNote) {
            $this->assertEquals($requester->email, $mail->to[0]['address']);
            $this->assertEquals("[SMART] Peminjaman Ditolak [{$req->request_number}]", $mail->envelope()->subject);
            $this->assertEquals($rejectionNote, $mail->reason);

            $rendered = $mail->render();
            $this->assertStringContainsString($rejectionNote, $rendered);
            $this->assertStringContainsString("Yth. {$requester->name}", $rendered);
            $this->assertStringContainsString("Peminjaman Anda dengan nomor", $rendered);
            $this->assertStringContainsString("<strong style=\"color: #dc2626;\">ditolak</strong> oleh <strong>{$manager->name}</strong>", $rendered);
            $this->assertStringContainsString("Lihat Detail Peminjaman", $rendered);
            $this->assertStringContainsString(url('/smart/history/' . $req->uuid), $rendered);

            return true;
        });
    }

    public function test_manager_can_bulk_action_requests(): void
    {
        Mail::fake();

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

        // 2 rejection emails sent for the 2 requests
        Mail::assertSent(RequesterRequestRejectedMail::class, 2);
    }
}
