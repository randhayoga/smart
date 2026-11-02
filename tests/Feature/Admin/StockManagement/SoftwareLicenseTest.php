<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Mail\SoftwareLicenseExpiringMail;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Notifications\AppNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SoftwareLicenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $softCategory;
    private Type $softType;
    private Category $compCategory;
    private Type $compType;
    private Brand $brand;
    private Uom $uom;
    private Organizer $organizer;
    private Vendor $vendor;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->create(['email' => 'admin.test@example.com']);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $permissions = ['inventory.view', 'inventory.manage'];
        foreach ($permissions as $permName) {
            $perm = Permission::firstOrCreate(['name' => $permName], ['label' => $permName, 'group' => 'inventory']);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $this->admin->assignRole($adminRole);

        $this->softCategory = Category::factory()->create([
            'code' => 'SOFT',
            'name' => 'Software',
        ]);
        $this->softType = Type::factory()->create([
            'category_id' => $this->softCategory->id,
            'code' => 'SOFT-OS',
            'name' => 'Operating System',
            'is_consumable' => false,
        ]);

        $this->compCategory = Category::factory()->create([
            'code' => 'COMP',
            'name' => 'Computer',
        ]);
        $this->compType = Type::factory()->create([
            'category_id' => $this->compCategory->id,
            'code' => 'COMP-PC',
            'name' => 'PC',
            'is_consumable' => false,
        ]);

        $this->brand = Brand::factory()->create(['name' => 'Microsoft']);
        $this->uom = Uom::factory()->create(['name' => 'Unit']);
        $this->organizer = Organizer::factory()->create(['name' => 'IT Dept']);
        $this->vendor = Vendor::factory()->create(['name' => 'Software Vendor']);
        $this->location = Location::factory()->create(['name' => 'Headquarters']);
    }

    public function test_can_create_software_asset_with_subscription_license_exp_date_and_reminder(): void
    {
        $payload = [
            'category_id' => $this->softCategory->id,
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Windows 11 Pro Enterprise',
            'model_specification' => 'Enterprise 64-bit OS',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-SOFT-001',
            'purchase_date' => now()->format('Y-m-d'),
            'input_asset' => true,
            'borrowing_type' => 'LT',
            'condition' => 'Bagus',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            'price' => '2500000',
            'license_exp_date' => '2027-12-31',
            'renewal_reminder' => 30,
        ];

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/new-stock', $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assets', [
            'license_exp_date' => '2027-12-31 00:00:00.000',
            'renewal_reminder' => 30,
            'location_id' => null,
            'condition' => null,
        ]);
    }

    public function test_can_create_software_asset_with_perpetual_license_null_date_and_reminder(): void
    {
        $payload = [
            'category_id' => $this->softCategory->id,
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Office 2021 Perpetual',
            'model_specification' => 'Lifetime license',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-SOFT-PERP',
            'purchase_date' => now()->format('Y-m-d'),
            'input_asset' => true,
            'borrowing_type' => 'LT',
            'condition' => 'Bagus',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            'price' => '4000000',
            'license_exp_date' => null,
            'renewal_reminder' => null,
        ];

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/new-stock', $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assets', [
            'classification' => 'Aset',
            'location_id' => null,
            'condition' => null,
            'license_exp_date' => null,
            'renewal_reminder' => null,
        ]);
    }

    public function test_non_software_asset_forces_license_exp_date_to_null(): void
    {
        $payload = [
            'category_id' => $this->compCategory->id,
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Dell Workstation',
            'model_specification' => 'Core i7',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-COMP-001',
            'purchase_date' => now()->format('Y-m-d'),
            'input_asset' => true,
            'asset_specification' => 'RAM 32GB',
            'borrowing_type' => 'LT',
            'condition' => 'Bagus',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            'price' => '15000000',
            'license_exp_date' => '2027-12-31',
            'renewal_reminder' => 30,
        ];

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/new-stock', $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assets', [
            'comp_specification' => 'RAM 32GB',
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'license_exp_date' => null,
            'renewal_reminder' => null,
        ]);
    }

    public function test_can_update_software_asset_license_and_reminder(): void
    {
        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Adobe Creative Cloud',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);
        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'license_exp_date' => '2026-12-31',
            'renewal_reminder' => 14,
        ]);

        $response = $this->actingAs($this->admin)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => '1200000',
            'burden' => 'Corporate',
            'license_exp_date' => '2027-06-30',
            'renewal_reminder' => 21,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'location_id' => null,
            'condition' => null,
            'license_exp_date' => '2027-06-30 00:00:00.000',
            'renewal_reminder' => 21,
        ]);
    }

    public function test_software_asset_can_be_created_with_null_location_and_condition(): void
    {
        $payload = [
            'category_id' => $this->softCategory->id,
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Figma Enterprise',
            'model_specification' => 'Cloud design suite',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-FIGMA-001',
            'purchase_date' => now()->format('Y-m-d'),
            'input_asset' => true,
            'borrowing_type' => 'LT',
            'condition' => null,
            'location_id' => null,
            'classification' => 'Aset',
            'price' => '10000000',
            'license_exp_date' => '2028-01-01',
            'renewal_reminder' => 14,
        ];

        $response = $this->actingAs($this->admin)->post('/smart/stock-management/new-stock', $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assets', [
            'location_id' => null,
            'condition' => null,
            'license_exp_date' => '2028-01-01 00:00:00.000',
            'renewal_reminder' => 14,
        ]);
    }

    public function test_artisan_command_notifies_admins_at_milestones(): void
    {
        Mail::fake();
        Notification::fake();

        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Software Pack',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $today = Carbon::now()->startOfDay();

        // 1. Asset expiring in 30 days -> in-app notification only
        $asset30 = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'license_exp_date' => $today->copy()->addDays(30)->format('Y-m-d'),
            'renewal_reminder' => 30,
        ]);

        // 2. Asset expiring in 7 days -> both in-app notification and email
        $asset7 = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'license_exp_date' => $today->copy()->addDays(7)->format('Y-m-d'),
            'renewal_reminder' => 14,
        ]);

        // 3. Asset expiring in 3 days -> daily in-app notification
        $asset3 = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'license_exp_date' => $today->copy()->addDays(3)->format('Y-m-d'),
            'renewal_reminder' => 10,
        ]);

        // 4. Asset with renewal_reminder == 0 -> NO notification
        $assetDisabled = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'license_exp_date' => $today->copy()->addDays(7)->format('Y-m-d'),
            'renewal_reminder' => 0,
        ]);

        // 5. Perpetual asset (null date) -> NO notification
        $assetPerp = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'license_exp_date' => null,
            'renewal_reminder' => null,
        ]);

        $this->artisan('licenses:check-expirations')
            ->assertExitCode(0);

        // Verify in-app notifications
        Notification::assertSentTo(
            $this->admin,
            AppNotification::class,
            function (AppNotification $n) use ($asset30) {
                return ($n->extra['asset_id'] ?? null) === $asset30->id
                    && ($n->extra['days_remaining'] ?? null) === 30;
            }
        );

        Notification::assertSentTo(
            $this->admin,
            AppNotification::class,
            function (AppNotification $n) use ($asset7) {
                return ($n->extra['asset_id'] ?? null) === $asset7->id
                    && ($n->extra['days_remaining'] ?? null) === 7;
            }
        );

        Notification::assertSentTo(
            $this->admin,
            AppNotification::class,
            function (AppNotification $n) use ($asset3) {
                return ($n->extra['asset_id'] ?? null) === $asset3->id
                    && ($n->extra['days_remaining'] ?? null) === 3;
            }
        );

        Notification::assertNotSentTo(
            $this->admin,
            AppNotification::class,
            function (AppNotification $n) use ($assetDisabled) {
                return ($n->extra['asset_id'] ?? null) === $assetDisabled->id;
            }
        );

        // Verify email sent ONLY for 7-day milestone
        Mail::assertSent(SoftwareLicenseExpiringMail::class, function (SoftwareLicenseExpiringMail $mail) use ($asset7) {
            return $mail->asset->id === $asset7->id && $mail->daysRemaining === 7;
        });

        Mail::assertNotSent(SoftwareLicenseExpiringMail::class, function (SoftwareLicenseExpiringMail $mail) use ($asset30) {
            return $mail->asset->id === $asset30->id;
        });
    }

    public function test_software_asset_past_expiration_date_automatically_transitions_to_lisensi_kedaluwarsa(): void
    {
        Notification::fake();
        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Legacy Software',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $yesterday = Carbon::yesterday();
        $expiredAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tersedia',
            'license_exp_date' => $yesterday->format('Y-m-d'),
        ]);

        $count = Asset::expireOverdueLicenses();
        $this->assertEquals(1, $count);

        $expiredAsset->refresh();
        $this->assertEquals('Lisensi Kedaluwarsa', $expiredAsset->status);

        // Verify lifecycle record was automatically created by updating hook
        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $expiredAsset->id,
            'status' => 'Lisensi Kedaluwarsa',
            'note' => "Lisensi software telah kedaluwarsa pada {$yesterday->format('d-m-Y')}.",
        ]);

        // Verify notification dispatched
        Notification::assertSentTo(
            $this->admin,
            AppNotification::class,
            function (AppNotification $n) use ($expiredAsset) {
                return ($n->extra['asset_id'] ?? null) === $expiredAsset->id
                    && ($n->extra['title_key'] ?? null) === 'inventory.notification_license_expired_title'
                    && $n->url === '/smart/stock-management/non-consumables?search=' . urlencode($expiredAsset->number);
            }
        );
    }

    public function test_cached_overdue_check_runs_only_once_per_calendar_day(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Cache::flush();

        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Antivirus Pro',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $yesterday = Carbon::yesterday();
        $expiredAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tersedia',
            'license_exp_date' => $yesterday->format('Y-m-d'),
        ]);

        // First run executes query and transitions
        $count1 = Asset::expireOverdueLicensesCached();
        $this->assertEquals(1, $count1);
        $this->assertEquals('Lisensi Kedaluwarsa', $expiredAsset->fresh()->status);

        // Create another overdue asset afterwards
        $anotherOverdue = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tersedia',
            'license_exp_date' => $yesterday->format('Y-m-d'),
        ]);

        // Second run on the same day hits cache and returns 0 without running DB update
        $count2 = Asset::expireOverdueLicensesCached();
        $this->assertEquals(0, $count2);
        $this->assertEquals('Tersedia', $anotherOverdue->fresh()->status);
    }

    public function test_expired_software_asset_transitions_to_lisensi_kedaluwarsa_on_activation_approval(): void
    {
        Notification::fake();

        $manager = User::factory()->create(['email' => 'manager.test@example.com']);
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['label' => 'Manager']);
        $permission = Permission::firstOrCreate(['name' => 'inventory.status_approval.decide'], ['label' => 'Decide Status Approval', 'group' => 'inventory']);
        $managerRole->permissions()->syncWithoutDetaching([$permission->id]);
        $manager->roles()->attach($managerRole);

        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Antivirus Expired',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $yesterday = Carbon::yesterday();
        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Belum Diverifikasi',
            'license_exp_date' => $yesterday->format('Y-m-d'),
        ]);

        $approval = AssetActivationApproval::create([
            'asset_id' => $asset->id,
            'requester_id' => $this->admin->id,
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('smart.approve-activation.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'note' => 'Disetujui untuk audit aset lama.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $asset->refresh();
        $this->assertEquals('Lisensi Kedaluwarsa', $asset->status);

        // Verify lifecycle record has Lisensi Kedaluwarsa status and explanatory note
        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $asset->id,
            'action_type' => 'Approval',
            'status' => 'Lisensi Kedaluwarsa',
            'actor_id' => $manager->id,
        ]);

        $lifecycle = AssetLifecycle::where('asset_id', $asset->id)->where('action_type', 'Approval')->first();
        $this->assertStringContainsString('Lisensi software telah kedaluwarsa', $lifecycle->note);
    }

    public function test_valid_software_asset_transitions_to_tersedia_on_activation_approval(): void
    {
        Notification::fake();

        $manager = User::factory()->create(['email' => 'manager2.test@example.com']);
        $managerRole = Role::firstOrCreate(['name' => 'manager2'], ['label' => 'Manager 2']);
        $permission = Permission::firstOrCreate(['name' => 'inventory.status_approval.decide'], ['label' => 'Decide Status Approval', 'group' => 'inventory']);
        $managerRole->permissions()->syncWithoutDetaching([$permission->id]);
        $manager->roles()->attach($managerRole);

        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Active Cloud Suite',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $future = Carbon::today()->addYear();
        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Belum Diverifikasi',
            'license_exp_date' => $future->format('Y-m-d'),
        ]);

        $approval = AssetActivationApproval::create([
            'asset_id' => $asset->id,
            'requester_id' => $this->admin->id,
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('smart.approve-activation.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'note' => 'Disetujui.',
        ]);

        $response->assertRedirect();
        $asset->refresh();
        $this->assertEquals('Tersedia', $asset->status);
    }

    public function test_editing_software_asset_to_expired_date_transitions_to_lisensi_kedaluwarsa(): void
    {
        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Desktop Suite',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tersedia',
            'license_exp_date' => Carbon::today()->addMonths(6)->format('Y-m-d'),
        ]);

        $yesterday = Carbon::yesterday();
        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->number), [
            'number' => $asset->number,
            'lot_id' => $lot->id,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'license_exp_date' => $yesterday->format('Y-m-d'),
            'renewal_reminder' => 30,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);

        $response->assertRedirect();
        $asset->refresh();
        $this->assertEquals('Lisensi Kedaluwarsa', $asset->status);
    }

    public function test_extending_expired_software_asset_transitions_to_tersedia_and_logs_single_status_change(): void
    {
        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Extended Suite Pro',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $yesterday = Carbon::yesterday();
        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Lisensi Kedaluwarsa',
            'license_exp_date' => $yesterday->format('Y-m-d'),
            'condition' => null,
            'location_id' => null,
        ]);

        $initialLifecycleCount = AssetLifecycle::where('asset_id', $asset->id)->count();

        $nextYear = Carbon::today()->addYear();
        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->number), [
            'number' => $asset->number,
            'lot_id' => $lot->id,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'license_exp_date' => $nextYear->format('Y-m-d'),
            'renewal_reminder' => 30,
            'status' => 'Lisensi Kedaluwarsa',
            'condition' => 'Bagus',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertEquals('Tersedia', $asset->status);
        $this->assertEquals($nextYear->format('Y-m-d'), $asset->license_exp_date->format('Y-m-d'));

        // Assert exactly one new lifecycle record was created
        $newLifecycles = AssetLifecycle::where('asset_id', $asset->id)
            ->orderBy('id', 'desc')
            ->take(AssetLifecycle::where('asset_id', $asset->id)->count() - $initialLifecycleCount)
            ->get();

        $this->assertCount(1, $newLifecycles);
        $log = $newLifecycles->first();

        $expectedDate = $nextYear->format('d-m-Y');
        $this->assertEquals('Perubahan status', $log->action_type);
        $this->assertEquals("Lisensi software telah diperpanjang hingga {$expectedDate}.", $log->note);
        $this->assertEquals('Tersedia', $log->status);
        $this->assertEquals(['status' => 'Lisensi Kedaluwarsa'], $log->previous_state);
        $this->assertEquals(['status' => 'Tersedia'], $log->new_state);

        // Ensure generic status change note was suppressed (not created)
        $genericLogCount = AssetLifecycle::where('asset_id', $asset->id)
            ->where('note', 'like', "%Status diubah dari 'Lisensi Kedaluwarsa' menjadi 'Tersedia'%")
            ->count();
        $this->assertEquals(0, $genericLogCount);
    }

    public function test_extending_valid_software_asset_before_expiry_logs_single_status_change(): void
    {
        $model = InventoryModel::factory()->create([
            'type_id' => $this->softType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Continuous Suite',
        ]);
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $currentExp = Carbon::today()->addMonth();
        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tersedia',
            'license_exp_date' => $currentExp->format('Y-m-d'),
            'condition' => null,
            'location_id' => null,
        ]);

        $initialLifecycleCount = AssetLifecycle::where('asset_id', $asset->id)->count();

        $extendedExp = Carbon::today()->addYears(2);
        $response = $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->number), [
            'number' => $asset->number,
            'lot_id' => $lot->id,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'license_exp_date' => $extendedExp->format('Y-m-d'),
            'renewal_reminder' => 30,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertEquals('Tersedia', $asset->status);

        $newLifecycles = AssetLifecycle::where('asset_id', $asset->id)
            ->orderBy('id', 'desc')
            ->take(AssetLifecycle::where('asset_id', $asset->id)->count() - $initialLifecycleCount)
            ->get();

        $this->assertCount(1, $newLifecycles);
        $log = $newLifecycles->first();

        $expectedDate = $extendedExp->format('d-m-Y');
        $this->assertEquals('Perubahan status', $log->action_type);
        $this->assertEquals("Lisensi software telah diperpanjang hingga {$expectedDate}.", $log->note);
    }
}
