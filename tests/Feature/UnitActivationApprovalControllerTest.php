<?php

namespace Tests\Feature;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitActivationApproval;
use App\Models\Inventory\UnitLifecycle;
use App\Models\Master\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Unit Activation Approval Controller Feature Tests
 *
 * Verifies newly registered asset units default to inactive/unverified condition, trigger
 * manager notifications and pending activation approvals, support admin pending listing,
 * manager approval/rejection workflows, and audit lifecycle records.
 */
class UnitActivationApprovalControllerTest extends TestCase
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

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'employee_id' => '255578',
            'username' => '255578',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $permissions = [
            'inventory.view',
            'inventory.manage',
            'inventory.status_approval.request',
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::firstOrCreate(['name' => $permName], ['label' => $permName, 'group' => 'inventory']);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $admin->assignRole($adminRole);
        return $admin;
    }

    private function createIfsManager(): User
    {
        $ifsManager = User::factory()->create();

        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        if (!$ifsEmployee) {
            $ifsOrg = HrdOrgchart::factory()->create([
                'employee_id' => $ifsManager->employee_id,
                'org_code' => 'IFS',
            ]);
            $ifsEmployee = HrdEmployee::factory()->create([
                'employee_id' => $ifsManager->employee_id,
                'orgchart_id' => $ifsOrg->id,
            ]);
        } else {
            $ifsOrg = HrdOrgchart::find($ifsEmployee->orgchart_id);
            if ($ifsOrg) {
                $ifsOrg->update([
                    'employee_id' => $ifsManager->employee_id,
                    'org_code' => 'IFS',
                ]);
            }
        }

        $role = Role::firstOrCreate(['name' => 'ifs_manager'], ['label' => 'IFS Manager']);
        $permission = Permission::firstOrCreate(
            ['name' => 'inventory.status_approval.decide'],
            ['label' => 'Approve or Reject Unit Status Changes', 'group' => 'inventory']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $ifsManager->assignRole($role);
        $ifsManager->refresh();

        return $ifsManager;
    }

    private function createStandardUser(): User
    {
        $user = User::factory()->create();
        $userRole = Role::firstOrCreate(['name' => 'user'], ['label' => 'Employee']);
        $user->assignRole($userRole);
        return $user;
    }

    private function createLot(): Lot
    {
        Storage::fake('local');
        $lot = Lot::factory()->create([
            'image_url' => 'lots/sample.jpg',
        ]);
        Storage::disk('local')->put('lots/sample.jpg', 'fake-image-bytes');
        return $lot;
    }

    public function test_creating_unit_defaults_to_unverified_condition_and_inactive_status_and_creates_approval(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $location = Location::factory()->create();

        $response = $this->actingAs($admin)->post(route('smart.inventory.units.store'), [
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 150000,
            'use_lot_image' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $unit = Unit::where('lot_id', $lot->id)->firstOrFail();
        $this->assertEquals('Tidak Aktif', $unit->status);
        $this->assertEquals('Belum Diverifikasi', $unit->condition);

        $this->assertDatabaseHas('unit_activation_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'decision' => 'pending',
            'approver_id' => null,
            'note' => null,
        ]);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit->id,
            'action_type' => 'Registrasi',
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
        ]);
    }

    public function test_creating_unit_sends_in_app_notification_to_ifs_manager(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $location = Location::factory()->create();

        $this->actingAs($admin)->post(route('smart.inventory.units.store'), [
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 150000,
            'use_lot_image' => true,
        ]);

        $unit = Unit::where('lot_id', $lot->id)->firstOrFail();

        $ifsManager->refresh();
        $this->assertNotEmpty($ifsManager->notifications);

        $notification = $ifsManager->notifications->first();
        $this->assertStringContainsString('Approval Aset Baru', $notification->data['title']);
        $this->assertStringContainsString($unit->number, $notification->data['message']);
        $this->assertEquals('warning', $notification->data['type']);
    }

    public function test_bulk_creating_units_sets_unverified_condition_and_creates_approvals(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $location = Location::factory()->create();

        $response = $this->actingAs($admin)->post(route('smart.inventory.units.bulk-store'), [
            'number' => '00001-BULK-ORG-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'bulk_quantity' => 3,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 100000,
            'use_lot_image' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $units = Unit::where('lot_id', $lot->id)->get();
        $this->assertCount(3, $units);

        foreach ($units as $unit) {
            $this->assertEquals('Tidak Aktif', $unit->status);
            $this->assertEquals('Belum Diverifikasi', $unit->condition);

            $this->assertDatabaseHas('unit_activation_approvals', [
                'unit_id' => $unit->id,
                'requester_id' => $admin->id,
                'decision' => 'pending',
            ]);
        }

        $ifsManager->refresh();
        $this->assertGreaterThanOrEqual(3, $ifsManager->notifications->count());
    }

    public function test_admin_can_view_pending_activation_list(): void
    {
        $admin = $this->createAdmin();
        $lot = $this->createLot();
        $unit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
        ]);

        UnitActivationApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('smart.inventory.pending-aktivasi'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DaftarPendingAktivasi')
            ->has('units')
            ->where('units.0.id', $unit->id)
            ->where('units.0.condition', 'Belum Diverifikasi')
        );
    }

    public function test_ifs_manager_can_view_perlu_approval_activation(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $unit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
        ]);

        $approval = UnitActivationApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($ifsManager)->get(route('smart.approve-activation'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Manager/PerluApproveAktivasi')
            ->has('approvals')
            ->where('approvals.0.id', $approval->id)
            ->where('approvals.0.decision', 'pending')
        );
    }

    public function test_ifs_manager_can_view_sudah_diproses_activation(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $unit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);

        $approval = UnitActivationApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'approver_id' => $ifsManager->id,
            'decision' => 'approved',
            'requested_at' => now()->subDay(),
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($ifsManager)->get(route('smart.approve-activation', ['history' => true]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Manager/SudahApproveAktivasi')
            ->has('approvals')
            ->where('approvals.0.id', $approval->id)
            ->where('approvals.0.decision', 'approved')
        );
    }

    public function test_non_manager_cannot_access_activation_approval_page(): void
    {
        $standardUser = $this->createStandardUser();

        $response = $this->actingAs($standardUser)->get(route('smart.approve-activation'));

        $response->assertForbidden();
    }

    public function test_ifs_manager_can_bulk_approve_activations_and_updates_unit_to_bagus_and_tersedia(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $unit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
        ]);

        $approval = UnitActivationApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-activation.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'note' => 'Dokumen lengkap dan aset telah diverifikasi.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $approval->refresh();
        $this->assertEquals('approved', $approval->decision);
        $this->assertEquals($ifsManager->id, $approval->approver_id);
        $this->assertNotNull($approval->decided_at);

        $unit->refresh();
        $this->assertEquals('Tersedia', $unit->status);
        $this->assertEquals('Bagus', $unit->condition);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit->id,
            'action_type' => 'Approval',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'actor_id' => $ifsManager->id,
        ]);

        $admin->refresh();
        $adminNotif = $admin->notifications->first();
        $this->assertNotNull($adminNotif);
        $this->assertStringContainsString('Disetujui', $adminNotif->data['title']);
        $this->assertEquals('success', $adminNotif->data['type']);
    }

    public function test_ifs_manager_can_bulk_reject_activations_and_updates_unit_to_verifikasi_ditolak(): void
    {
        $admin = $this->createAdmin();
        $ifsManager = $this->createIfsManager();
        $lot = $this->createLot();
        $unit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'status' => 'Tidak Aktif',
            'condition' => 'Belum Diverifikasi',
        ]);

        $approval = UnitActivationApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $admin->id,
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-activation.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'rejected',
            'note' => 'Serial number fisik tidak sesuai data LOT.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $approval->refresh();
        $this->assertEquals('rejected', $approval->decision);
        $this->assertEquals($ifsManager->id, $approval->approver_id);
        $this->assertNotNull($approval->decided_at);

        $unit->refresh();
        $this->assertEquals('Tidak Aktif', $unit->status);
        $this->assertEquals('Verifikasi Ditolak', $unit->condition);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit->id,
            'action_type' => 'Approval',
            'status' => 'Tidak Aktif',
            'condition' => 'Verifikasi Ditolak',
            'actor_id' => $ifsManager->id,
        ]);

        $admin->refresh();
        $adminNotif = $admin->notifications->first();
        $this->assertNotNull($adminNotif);
        $this->assertStringContainsString('Ditolak', $adminNotif->data['title']);
        $this->assertEquals('error', $adminNotif->data['type']);
    }
}
