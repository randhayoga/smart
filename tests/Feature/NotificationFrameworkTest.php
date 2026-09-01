<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notification Framework Feature Tests
 *
 * Verifies real-time notifications, low stock threshold alerts, manager status approval triggers, and user/role dispatching.
 */
class NotificationFrameworkTest extends TestCase
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

    public function test_consumable_low_stock_notification_sent_to_admins_when_stock_at_or_below_threshold(): void
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        /** @var AdmUser $admin */
        $admin = AdmUser::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        /** @var AdmUser $standardUser */
        $standardUser = AdmUser::factory()->create();

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Kertas A4 80gr',
            'min_stock_threshold' => 10,
        ]);

        Lot::factory()->create([
            'barang_id' => $barang->id,
            'current_quantity' => 5,
        ]);

        $service = app(NotificationService::class);
        $sent = $service->checkAndNotifyLowStock($barang);

        $this->assertTrue($sent);

        $admin->refresh();
        $standardUser->refresh();

        $this->assertCount(1, $admin->notifications);
        $this->assertCount(0, $standardUser->notifications);

        $notif = $admin->notifications->first();
        $this->assertStringContainsString('Peringatan Stok Minimum:', $notif->data['title']);
        $this->assertStringContainsString($barang->name, $notif->data['title']);
        $this->assertEquals('warning', $notif->data['type']);
        $cleanNumber = preg_replace('/[^a-zA-Z0-9]/', '', (string)$barang->number);
        $this->assertEquals('/smart/inventory/stok-habis-pakai/' . $cleanNumber, $notif->data['url']);
        $this->assertEquals($barang->id, $notif->data['extra']['barang_id']);
        $this->assertEquals(5, $notif->data['extra']['current_stock']);
        $this->assertEquals(10, $notif->data['extra']['min_stock_threshold']);
    }

    public function test_consumable_low_stock_notification_not_sent_when_stock_above_threshold(): void
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        /** @var AdmUser $admin */
        $admin = AdmUser::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'min_stock_threshold' => 10,
        ]);

        Lot::factory()->create([
            'barang_id' => $barang->id,
            'current_quantity' => 15,
        ]);

        $service = app(NotificationService::class);
        $sent = $service->checkAndNotifyLowStock($barang);

        $this->assertFalse($sent);
        $this->assertCount(0, $admin->notifications);
    }

    public function test_check_all_consumable_low_stock(): void
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        /** @var AdmUser $admin */
        $admin = AdmUser::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);

        $barang1 = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'min_stock_threshold' => 10,
        ]);
        Lot::factory()->create(['barang_id' => $barang1->id, 'current_quantity' => 3]);

        $barang2 = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'min_stock_threshold' => 5,
        ]);
        Lot::factory()->create(['barang_id' => $barang2->id, 'current_quantity' => 10]);

        $service = app(NotificationService::class);
        $count = $service->checkAllConsumableLowStock();

        $this->assertEquals(1, $count);
        $this->assertCount(1, $admin->notifications);
    }

    public function test_manually_updating_current_quantity_triggers_low_stock_notification(): void
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        /** @var AdmUser $admin */
        $admin = AdmUser::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Spidol Boardmarker',
            'min_stock_threshold' => 10,
        ]);

        $lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'initial_quantity' => 20,
            'current_quantity' => 20,
        ]);

        $this->assertCount(0, $admin->notifications);

        // Manually update current_quantity from 20 to 5 (below threshold 10)
        $response = $this->actingAs($admin)->put(route('smart.inventory.lots.update', $lot), [
            'number' => $lot->number,
            'barang_id' => $barang->id,
            'organizer_id' => $lot->organizer_id,
            'vendor_id' => $lot->vendor_id,
            'location_id' => $lot->location_id,
            'po_number' => $lot->po_number,
            'date_of_receipt' => $lot->date_of_receipt->format('Y-m-d'),
            'current_quantity' => 5,
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertCount(1, $admin->notifications);

        $notif = $admin->notifications->first();
        $this->assertStringContainsString('Peringatan Stok Minimum:', $notif->data['title']);
        $this->assertStringContainsString($barang->name, $notif->data['title']);
        $this->assertEquals(5, $notif->data['extra']['current_stock']);
        $this->assertEquals(10, $notif->data['extra']['min_stock_threshold']);
    }

    public function test_notify_ifs_manager_when_asset_switched_to_pending_dm(): void
    {
        Storage::fake('local');
        /** @var AdmUser $ifsManager */
        $ifsManager = AdmUser::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => 'IFS',
        ]);
        $ifsManager->refresh();

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'Laptop Inspiron']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-DEL-001', 'status' => 'Pending:BoD/BoC']);

        /** @var AdmUser $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        $admin = AdmUser::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        $file = UploadedFile::fake()->create('bod_approval.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->put(route('smart.inventory.units.update', $unit), [
            'bod_boc_approval_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertEquals('Pending:DM', $unit->status);

        $ifsManager->refresh();
        $this->assertCount(1, $ifsManager->notifications);

        $notif = $ifsManager->notifications->first();
        $this->assertEquals('Penghapusan Aset Dell Laptop Inspiron: Perlu Perhatian Anda', $notif->data['title']);
        $this->assertEquals('Penghapusan aset AST-DEL-001 telah disetujui oleh BoD/BoC dan sekarang memerlukan approval Anda', $notif->data['message']);
        $this->assertEquals('/smart/approve-status?search=AST-DEL-001', $notif->data['url']);
    }

    public function test_notify_admin_when_dm_ifs_approves_asset_status(): void
    {
        /** @var AdmUser $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        $admin = AdmUser::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        /** @var AdmUser $ifsManager */
        $ifsManager = AdmUser::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => 'IFS',
        ]);

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'Laptop XPS']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-XPS-001', 'status' => 'Pending:DM', 'condition' => 'Bagus']);

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/test.pdf',
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-status.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'note' => 'Disetujui untuk dihapus',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertCount(1, $admin->notifications);

        $notif = $admin->notifications->first();
        $this->assertEquals('Penghapusan Aset Dell Laptop XPS Disetujui DM IFS', $notif->data['title']);
        $this->assertEquals('Status aset AST-XPS-001 telah berubah menjadi Tidak Aktif dan kondisi berubah menjadi Rusak Total.', $notif->data['message']);
        $this->assertEquals('success', $notif->data['type']);
        $this->assertEquals('/smart/inventory/assets?search=AST-XPS-001', $notif->data['url']);
    }

    public function test_notify_admin_when_dm_ifs_rejects_asset_status(): void
    {
        /** @var AdmUser $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        $admin = AdmUser::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        /** @var AdmUser $ifsManager */
        $ifsManager = AdmUser::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => 'IFS',
        ]);

        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'ThinkPad']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-LNV-002', 'status' => 'Pending:DM', 'condition' => 'Bagus']);

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/test.pdf',
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-status.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'rejected',
            'note' => 'Dokumen tidak lengkap',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertEquals('Tersedia', $unit->status);
        $this->assertEquals('Bagus', $unit->condition);

        $admin->refresh();
        $this->assertCount(1, $admin->notifications);

        $notif = $admin->notifications->first();
        $this->assertEquals('Penghapusan Aset Lenovo ThinkPad Ditolak DM IFS', $notif->data['title']);
        $this->assertEquals('Status aset AST-LNV-002 telah dikembalikan menjadi Tersedia dan kondisi dikembalikan menjadi Bagus.', $notif->data['message']);
        $this->assertEquals('error', $notif->data['type']);
        $this->assertEquals('/smart/inventory/assets?search=AST-LNV-002', $notif->data['url']);
    }
}
