<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFrameworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
    }

    public function test_min_stock_threshold_attribute_on_barang(): void
    {
        $barang = Barang::factory()->create([
            'min_stock_threshold' => 15,
        ]);

        $this->assertEquals(15, $barang->min_stock_threshold);
    }

    public function test_notification_service_send_to_user(): void
    {
        /** @var AdmUser $user */
        $user = AdmUser::factory()->create();
        $service = app(NotificationService::class);

        $service->sendToUser(
            $user,
            'Perlu Approval Status',
            'Aset LAPTOP-001 membutuhkan persetujuan',
            'warning',
            '/smart/approve-status'
        );

        $this->assertCount(1, $user->notifications);
        $notification = $user->notifications->first();
        $this->assertEquals('Perlu Approval Status', $notification->data['title']);
        $this->assertEquals('warning', $notification->data['type']);
        $this->assertEquals('/smart/approve-status', $notification->data['url']);
        $this->assertNull($notification->read_at);
    }

    public function test_notification_service_send_to_role(): void
    {
        // 1. Setup an IFS Manager
        /** @var AdmUser $ifsManager */
        $ifsManager = AdmUser::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => 'IFS',
        ]);
        $ifsManager->refresh();

        // 2. Setup a standard user
        /** @var AdmUser $standardUser */
        $standardUser = AdmUser::factory()->create();

        // Send role-based notification to IFS Manager
        $service = app(NotificationService::class);
        $service->sendToRole(
            'ifs_manager',
            'Perlu approval status: AST-001 - Laptop Dell',
            'Perlu persetujuan perubahan status aset',
            'warning'
        );

        $ifsManager->refresh();
        $standardUser->refresh();

        $this->assertCount(1, $ifsManager->notifications);
        $this->assertCount(0, $standardUser->notifications);

        $notif = $ifsManager->notifications->first();
        $this->assertEquals('Perlu approval status: AST-001 - Laptop Dell', $notif->data['title']);
    }

    public function test_notification_controller_api_endpoints(): void
    {
        /** @var AdmUser $user */
        $user = AdmUser::factory()->create();
        $service = app(NotificationService::class);

        $service->sendToUser($user, 'Notif 1', 'Pesan 1', 'info');
        $service->sendToUser($user, 'Notif 2', 'Pesan 2', 'warning');

        // 1. Fetch via API
        $response = $this->actingAs($user)->getJson(route('smart.notifications.index'));
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'notifications');
        $response->assertJson(['unreadCount' => 2]);

        $notifId = $user->notifications->first()->id;

        // 2. Mark single notification as read
        $response = $this->actingAs($user)->postJson(route('smart.notifications.read', ['id' => $notifId]));
        $response->assertStatus(200);
        $this->assertNotNull($user->notifications()->where('id', $notifId)->first()->read_at);

        // 3. Mark all as read
        $response = $this->actingAs($user)->postJson(route('smart.notifications.read-all'));
        $response->assertStatus(200);
        $this->assertEquals(0, $user->unreadNotifications()->count());

        // 4. Clear all notifications
        $response = $this->actingAs($user)->deleteJson(route('smart.notifications.clear'));
        $response->assertStatus(200);
        $user->refresh();
        $this->assertCount(0, $user->notifications);
    }
}
