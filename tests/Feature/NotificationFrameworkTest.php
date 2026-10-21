<?php

namespace Tests\Feature;

use App\Mail\DMBulkAssetDeactivationRequest;
use App\Mail\DMAssetStatusRequest;
use App\Models\User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\AssetStatusApproval;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();
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
        /** @var User $user */
        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $service->sendToUser(
            $user,
            'Perlu Approval Status',
            'Aset LAPTOP-001 membutuhkan persetujuan',
            'warning',
            '/smart/approve-deactivation'
        );

        $this->assertCount(1, $user->notifications);
        $notification = $user->notifications->first();
        $this->assertEquals('Perlu Approval Status', $notification->data['title']);
        $this->assertEquals('warning', $notification->data['type']);
        $this->assertEquals('/smart/approve-deactivation', $notification->data['url']);
        $this->assertNull($notification->read_at);
    }

    public function test_notification_service_send_to_role(): void
    {
        // 1. Setup an IFS Manager
        /** @var User $ifsManager */
        $ifsManager = User::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => User::getIfsOrgCode(),
        ]);
        $ifsManager->refresh();

        // 2. Setup a standard user
        /** @var User $standardUser */
        $standardUser = User::factory()->create();

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
        /** @var User $user */
        $user = User::factory()->create();
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
        $employee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        /** @var User $admin */
        $admin = User::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        /** @var User $standardUser */
        $standardUser = User::factory()->create();

        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => true]);
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
        $this->assertEquals('/smart/stock-management/consumables/' . $cleanNumber, $notif->data['url']);
        $this->assertEquals($barang->id, $notif->data['extra']['barang_id']);
        $this->assertEquals(5, $notif->data['extra']['current_stock']);
        $this->assertEquals(10, $notif->data['extra']['min_stock_threshold']);
    }

    public function test_consumable_low_stock_notification_not_sent_when_stock_above_threshold(): void
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        /** @var User $admin */
        $admin = User::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => true]);
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
        $employee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        /** @var User $admin */
        $admin = User::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => true]);

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
        $employee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        /** @var User $admin */
        $admin = User::factory()->create([
            'employee_id' => $employee->employee_id,
        ]);

        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => true]);
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

        // Deduct consumable quantity from 20 to 5 (below threshold 10) via manual request
        $requester = User::factory()->create();
        $orgchart = HrdOrgchart::factory()->create();
        $response = $this->actingAs($admin)->post(route('smart.inventory.lots.manual-request', $lot), [
            'user_id' => $requester->id,
            'request_date' => now()->format('Y-m-d'),
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'quantity' => 15,
            'note' => 'Manual deduction',
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
        /** @var User $ifsManager */
        $ifsManager = User::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => User::getIfsOrgCode(),
        ]);
        $ifsManager->refresh();

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'Laptop Inspiron']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-DEL-001', 'status' => 'Tersedia', 'condition' => 'Bagus']);

        /** @var User $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        $file = UploadedFile::fake()->create('memo.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->put(route('smart.inventory.assets.update', $unit), [
            'number' => $unit->number,
            'lot_id' => $unit->lot_id,
            'location_id' => $unit->location_id,
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 100000,
            'memo_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertEquals('Tersedia,Pending:DM', $unit->status);
        $this->assertEquals('Rusak Total', $unit->condition);

        $ifsManager->refresh();
        $this->assertCount(1, $ifsManager->notifications);

        $notif = $ifsManager->notifications->first();
        $this->assertEquals('Penghapusan Aset Dell Laptop Inspiron: Perlu Perhatian Anda', $notif->data['title']);
        $this->assertEquals('Pengajuan penghapusan aset AST-DEL-001 telah diajukan dan memerlukan approval Anda', $notif->data['message']);
        $this->assertEquals('/smart/approve-deactivation?search=AST-DEL-001', $notif->data['url']);
    }

    public function test_notify_admin_when_dm_ifs_approves_asset_status(): void
    {
        /** @var User $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        /** @var User $ifsManager */
        $ifsManager = User::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => User::getIfsOrgCode(),
        ]);

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'Laptop XPS']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-XPS-001', 'status' => 'Tersedia,Pending:DM', 'condition' => 'Rusak Total']);

        $approval = AssetStatusApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $admin->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/test.pdf',
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-deactivation.bulk-store'), [
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
        $this->assertEquals('Status aset AST-XPS-001 telah berubah menjadi Tidak Aktif, Pending:BoD/BoC.', $notif->data['message']);
        $this->assertEquals('success', $notif->data['type']);
        $this->assertEquals('/smart/stock-management/non-consumables?search=AST-XPS-001', $notif->data['url']);
    }

    public function test_notify_admin_when_dm_ifs_rejects_asset_status(): void
    {
        /** @var User $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        /** @var User $ifsManager */
        $ifsManager = User::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => User::getIfsOrgCode(),
        ]);

        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'ThinkPad']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-LNV-002', 'status' => 'Tersedia,Pending:DM', 'condition' => 'Hilang']);

        $approval = AssetStatusApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $admin->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/test.pdf',
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-deactivation.bulk-store'), [
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
        $this->assertEquals('/smart/stock-management/non-consumables?search=AST-LNV-002', $notif->data['url']);
    }

    public function test_bulk_updating_assets_to_damaged_or_lost_sends_single_consolidated_batch_email_to_ifs_manager(): void
    {
        Storage::fake('local');

        /** @var User $ifsManager */
        $ifsManager = User::factory()->create(['email' => 'ifs.manager@example.com']);
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => User::getIfsOrgCode(),
        ]);

        /** @var User $admin */
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);
        $adminRole = \App\Models\Auth\Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $managePerm = \App\Models\Auth\Permission::firstOrCreate(['name' => 'inventory.manage'], ['label' => 'Manage Inventory', 'group' => 'inventory']);
        $adminRole->permissions()->syncWithoutDetaching([$managePerm->id]);
        $admin->assignRole($adminRole);
        $admin->refresh();

        $brand = Brand::factory()->create(['name' => 'Apple']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'MacBook Pro']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit1 = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-MBP-001', 'status' => 'Tersedia', 'condition' => 'Bagus']);
        $unit2 = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-MBP-002', 'status' => 'Tersedia', 'condition' => 'Bagus']);
        $unit3 = Unit::factory()->create(['lot_id' => $lot->id, 'number' => 'AST-MBP-003', 'status' => 'Tersedia', 'condition' => 'Bagus']);

        $memoFile = UploadedFile::fake()->create('bulk_memo.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->post(route('smart.inventory.assets.bulk-update'), [
            'ids' => [$unit1->id, $unit2->id, $unit3->id],
            'condition' => 'Rusak Total',
            'memo_file' => $memoFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 1. Assert exactly ONE batch email was sent per IFS manager, NOT 3 separate emails
        Mail::assertSent(DMBulkAssetDeactivationRequest::class, function ($mail) {
            return $mail->hasTo('ifs.manager@example.com')
                && $mail->count === 3
                && $mail->proposedCondition === 'Rusak Total'
                && count($mail->sampleAssets) === 3;
        });
        Mail::assertNotSent(DMAssetStatusRequest::class);

        // 2. Assert exactly ONE consolidated in-app notification was dispatched to IFS Manager
        $ifsManager->refresh();
        $this->assertCount(1, $ifsManager->notifications);

        $notif = $ifsManager->notifications->first();
        $this->assertEquals('warning', $notif->data['type']);
        $this->assertEquals('deactivation_approval_bulk', $notif->data['extra']['type']);
        $this->assertEquals(3, $notif->data['extra']['count']);
        $this->assertEquals('/smart/approve-deactivation', $notif->data['url']);
    }

    public function test_bulk_deactivation_notification_supports_english_locale(): void
    {
        /** @var User $ifsManager */
        $ifsManager = User::factory()->create(['email' => 'ifs.manager@example.com']);
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => User::getIfsOrgCode(),
        ]);

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $barang = Barang::factory()->create(['brand_id' => $brand->id, 'name' => 'Latitude']);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $units = Unit::factory()->count(2)->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'condition' => 'Bagus']);

        $originalLocale = app()->getLocale();
        try {
            app()->setLocale('en');

            app(NotificationService::class)->notifyIfsManagerBulkPendingDm($units, 'Rusak Total');

            $ifsManager->refresh();
            $this->assertCount(1, $ifsManager->notifications);

            $notif = $ifsManager->notifications->first();
            $this->assertEquals('Asset Deactivation Approval: 2 Units (Rusak Total)', $notif->data['title']);
            $this->assertEquals('2 asset units have been submitted for deactivation (Rusak Total) and require your approval.', $notif->data['message']);
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
