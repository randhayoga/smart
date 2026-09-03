<?php

namespace Tests\Feature;

use App\Mail\ManagerRequestApprovalMail;
use App\Mail\RequesterRequestRejectedMail;
use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Manager Request Email and One-Click Signed Approval Feature Tests
 *
 * Verifies email dispatch, HMAC-signed URL generation, scanner-safe GET handling,
 * external zero-login approval/rejection (POST), and tampered signature rejection.
 */
class ManagerRequestEmailApprovalTest extends TestCase
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
        $employee = HrdEmployee::factory()->create(['email' => 'manager@test.com']);
        $manager = AdmUser::factory()->create(['employee_id' => $employee->employee_id]);
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $manager->employee_id]);
        return $manager;
    }

    private function createRequester(): AdmUser
    {
        return AdmUser::factory()->create();
    }

    private function createSmartRequestRecord(AdmUser $requester, AdmUser $manager): SmartRequest
    {
        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Laptop ThinkPad T14',
        ]);

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000001',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Kebutuhan dinas luar kota',
            'status' => 'wait',
        ]);

        RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'quantity_requested' => 1,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(3),
        ]);

        return $req;
    }

    public function test_email_is_dispatched_with_valid_signed_url_to_manager(): void
    {
        Mail::fake();

        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);

        app(NotificationService::class)->notifyManagerNewRequest($req, $manager, 'Peminjaman');

        Mail::assertSent(ManagerRequestApprovalMail::class, function ($mail) use ($manager, $req) {
            $this->assertEquals($manager->email, $mail->to[0]['address']);
            $this->assertEquals("[SMART] Permohonan Persetujuan: Peminjaman Baru [{$req->request_number}]", $mail->envelope()->subject);
            $this->assertEquals(url('/smart/approve') . '?search=' . urlencode($req->request_number), $mail->loginUrl);

            // Ensure rendering blade view succeeds with borrow period
            $rendered = $mail->render();
            $this->assertNotEmpty($rendered);

            // Verify actionUrl is a valid HMAC-signed route
            $requestForUrl = \Illuminate\Http\Request::create($mail->actionUrl);
            $this->assertTrue(URL::hasValidSignature($requestForUrl));

            return true;
        });
    }

    public function test_scanner_safe_get_does_not_mutate_request_status(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);

        $signedUrl = URL::temporarySignedRoute(
            'smart.external-approval.show',
            now()->addHours(48),
            ['request' => $req->id]
        );

        // Simulated corporate email scanner crawling the link (unauthenticated)
        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Manager/ExternalApproval')
            ->where('request.number', $req->request_number)
            ->where('request.rawStatus', 'wait')
            ->where('request.items.0.name', fn ($name) => str_contains($name, 'Laptop ThinkPad T14'))
        );

        // Status MUST still be 'wait'
        $req->refresh();
        $this->assertEquals('wait', $req->status);
    }

    public function test_manager_can_approve_via_external_post_action(): void
    {
        Mail::fake();

        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);

        $signedUrl = URL::temporarySignedRoute(
            'smart.external-approval.action',
            now()->addHours(48),
            ['request' => $req->id]
        );

        // Submitting approval without active login session
        $response = $this->post($signedUrl, [
            'action' => 'approve',
            'note' => 'Disetujui untuk dinas',
        ]);

        $response->assertRedirect($signedUrl);

        $req->refresh();
        $this->assertEquals('approve', $req->status);

        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $req->id,
            'approver_id' => $manager->id,
            'decision' => 'approve',
            'note' => 'Disetujui untuk dinas',
        ]);

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'wait',
            'status_to' => 'approve',
            'changed_by' => $manager->id,
        ]);

        // Requester receives in-app notification
        $notification = $requester->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals("{$req->type_name} {$req->request_number} Disetujui", $notification->data['title']);
        $this->assertEquals("{$manager->name} menyetujui {$req->type_name} Anda dan sekarang sedang diproses oleh Tim Aset.", $notification->data['message']);
        $this->assertEquals('success', $notification->data['type']);
        $this->assertEquals("/smart/history/{$req->uuid}", $notification->data['url']);

        Mail::assertNothingSent();
    }

    public function test_manager_can_reject_via_external_post_action(): void
    {
        Mail::fake();

        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);

        $signedUrl = URL::temporarySignedRoute(
            'smart.external-approval.action',
            now()->addHours(48),
            ['request' => $req->id]
        );

        // Submitting rejection without active login session
        $rejectionNote = 'Unit sedang diperlukan untuk tim inti';
        $response = $this->post($signedUrl, [
            'action' => 'reject',
            'note' => $rejectionNote,
        ]);

        $response->assertRedirect($signedUrl);

        $req->refresh();
        $this->assertEquals('reject', $req->status);

        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $req->id,
            'approver_id' => $manager->id,
            'decision' => 'reject',
            'note' => $rejectionNote,
        ]);

        // Requester receives in-app notification
        $notification = $requester->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals("{$req->type_name} {$req->request_number} Ditolak", $notification->data['title']);
        $this->assertEquals("{$manager->name} menolak {$req->type_name} Anda, alasan penolakan dapat dilihat pada detail {$req->type_name}.", $notification->data['message']);
        $this->assertEquals('error', $notification->data['type']);
        $this->assertEquals("/smart/history/{$req->uuid}", $notification->data['url']);

        // Requester receives rejection email
        Mail::assertSent(RequesterRequestRejectedMail::class, function ($mail) use ($requester, $req, $manager, $rejectionNote) {
            $this->assertEquals($requester->email, $mail->to[0]['address']);
            $this->assertEquals("[SMART] {$req->type_name} Ditolak [{$req->request_number}]", $mail->envelope()->subject);
            $this->assertEquals($rejectionNote, $mail->reason);

            return true;
        });
    }

    public function test_tampered_signature_returns_forbidden(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);

        $validUrl = URL::temporarySignedRoute(
            'smart.external-approval.show',
            now()->addHours(48),
            ['request' => $req->id]
        );

        // Alter the request ID parameter in the URL
        $tamperedUrl = str_replace("external-approval/{$req->id}", "external-approval/9999", $validUrl);

        $response = $this->get($tamperedUrl);
        $response->assertStatus(403);
    }

    public function test_expired_signed_url_returns_forbidden(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);

        // Generate expired signed URL (expired 1 minute ago)
        $expiredUrl = URL::temporarySignedRoute(
            'smart.external-approval.show',
            now()->subMinute(),
            ['request' => $req->id]
        );

        $response = $this->get($expiredUrl);
        $response->assertStatus(403);
    }

    public function test_opening_already_processed_request_shows_processed_view(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();
        $req = $this->createSmartRequestRecord($requester, $manager);
        $req->update(['status' => 'approve']);

        $signedUrl = URL::temporarySignedRoute(
            'smart.external-approval.show',
            now()->addHours(48),
            ['request' => $req->id]
        );

        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Manager/ExternalApproval')
            ->where('request.rawStatus', 'approve')
        );
    }

    public function test_email_and_external_page_render_project_format_and_subcategory_uom(): void
    {
        $manager = $this->createManager();
        $requester = $this->createRequester();

        $project = \App\Models\TbProject::factory()->create([
            'no_project' => 'PRJ-888',
            'project_name' => 'Project Tower Alpha',
        ]);

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id, 'name' => 'Kabel UTP Cat6']);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create(['name' => 'Roll']);
        Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Kabel Belden',
        ]);

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000099',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'project',
            'project_id' => $project->id,
            'reasoning' => 'Instalasi jaringan server',
            'status' => 'wait',
        ]);

        // Request item with only subcategory_id (generic request)
        RequestItem::create([
            'request_id' => $req->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 5,
        ]);

        $mail = new ManagerRequestApprovalMail($req, $manager, 'Permintaan');
        $this->assertEquals("Project PRJ-888 (Project Tower Alpha)", $mail->destinationName);
        $this->assertEquals("Roll", $mail->formattedItems[0]['uom']);
        $this->assertEquals("[SMART] Permohonan Persetujuan: Permintaan Baru [REQ-0000099]", $mail->envelope()->subject);

        $renderedHtml = $mail->render();
        $this->assertStringContainsString('Project PRJ-888 (Project Tower Alpha)', $renderedHtml);
        $this->assertStringContainsString('Roll', $renderedHtml);

        $signedUrl = URL::temporarySignedRoute(
            'smart.external-approval.show',
            now()->addHours(48),
            ['request' => $req->id]
        );

        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Manager/ExternalApproval')
            ->where('request.destination', 'Project PRJ-888 (Project Tower Alpha)')
            ->where('request.items.0.uom', 'Roll')
        );
    }
}
