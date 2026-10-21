<?php

namespace Tests\Feature;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\AssetStatusApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Models\User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asset Status Approval Feature Tests
 *
 * Verifies role-based access control and batch approve/reject actions for asset condition/deactivation requests.
 */
class AssetStatusApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable test bypass to ensure middleware checks roles
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    private function createIfsManager(): User
    {
        $ifsManager = User::factory()->create();

        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        if (!$ifsEmployee) {
            $ifsOrg = HrdOrgchart::factory()->create([
                'employee_id' => $ifsManager->employee_id,
                'org_code' => User::getIfsOrgCode(),
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
                    'org_code' => User::getIfsOrgCode(),
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

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'employee_id' => '255578',
            'username' => '255578',
        ]);
        $role = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $permission = Permission::firstOrCreate(
            ['name' => 'inventory.status_approval.request'],
            ['label' => 'Submit Unit Status Change Requests', 'group' => 'inventory']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $admin->assignRole($role);
        $admin->refresh();

        return $admin;
    }

    private function createDepartmentManager(): User
    {
        $managerUser = User::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);
        $role = Role::firstOrCreate(['name' => 'manager'], ['label' => 'Department / Project Manager']);
        $permission = Permission::firstOrCreate(
            ['name' => 'requests.approve'],
            ['label' => 'Approve or Reject Subordinate Requisitions', 'group' => 'requests']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $managerUser->assignRole($role);
        $managerUser->refresh();

        return $managerUser;
    }

    private function createUnit()
    {
        $lot = Lot::factory()->create();
        return Asset::create([
            'number' => substr($lot->number, 0, 20) . '-U01',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => $lot->unit_price,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);
    }

    public function test_only_ifs_manager_with_status_approval_decide_permission_can_access_pages(): void
    {
        $user = User::factory()->create();
        $admin = $this->createAdmin();
        $deptManager = $this->createDepartmentManager();
        $ifsManager = $this->createIfsManager();

        // Standard user -> 403
        $this->actingAs($user)->get(route('smart.approve-deactivation'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.approve-deactivation', ['history' => 'true']))->assertStatus(403);

        // Admin -> 403 (Admin is requester, not approval authority)
        $this->actingAs($admin)->get(route('smart.approve-deactivation'))->assertStatus(403);
        $this->actingAs($admin)->get(route('smart.approve-deactivation', ['history' => 'true']))->assertStatus(403);

        // Department Manager -> 403 (General manager only approves requisitions, not asset lifecycle)
        $this->actingAs($deptManager)->get(route('smart.approve-deactivation'))->assertStatus(403);
        $this->actingAs($deptManager)->get(route('smart.approve-deactivation', ['history' => 'true']))->assertStatus(403);

        // IFS Manager -> 200
        $this->actingAs($ifsManager)->get(route('smart.approve-deactivation'))->assertStatus(200);
        $this->actingAs($ifsManager)->get(route('smart.approve-deactivation', ['history' => 'true']))->assertStatus(200);
    }

    public function test_manager_can_bulk_approve_status(): void
    {
        $ifsManager = $this->createIfsManager();
        $admin = $this->createAdmin();
        $deptManager = $this->createDepartmentManager();
        $unit1 = $this->createUnit();
        $unit2 = $this->createUnit();

        $unit1->update(['condition' => 'Hilang', 'status' => 'Tersedia,Pending:DM']);
        $app1 = AssetStatusApproval::create([
            'asset_id' => $unit1->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
            'lost_doc_url' => 'lost_docs/placeholder.pdf',
        ]);

        $unit2->update(['condition' => 'Rusak Total', 'status' => 'Tersedia,Pending:DM']);
        $app2 = AssetStatusApproval::create([
            'asset_id' => $unit2->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
            'lost_doc_url' => null,
        ]);

        // Unauthorized accounts cannot perform bulk approval
        $this->actingAs($admin)->post(route('smart.approve-deactivation.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'approved',
            'note' => 'Unauthorized admin attempt',
        ])->assertStatus(403);

        $this->actingAs($deptManager)->post(route('smart.approve-deactivation.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'approved',
            'note' => 'Unauthorized manager attempt',
        ])->assertStatus(403);

        // Authorized IFS Manager can perform bulk approval
        $response = $this->actingAs($ifsManager)->post(route('smart.approve-deactivation.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'approved',
            'note' => 'Bulk approval works',
        ]);

        $response->assertRedirect();
        
        $app1->refresh();
        $app2->refresh();
        $unit1->refresh();
        $unit2->refresh();

        $this->assertEquals('approved', $app1->decision);
        $this->assertEquals('approved', $app2->decision);
        
        $this->assertEquals('Tidak Aktif,Pending:BoD/BoC', $unit1->status);
        $this->assertEquals('Hilang', $unit1->condition);

        $this->assertEquals('Tidak Aktif,Pending:BoD/BoC', $unit2->status);
        $this->assertEquals('Rusak Total', $unit2->condition);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit1->id,
            'condition' => 'Hilang',
            'actor_id' => $app1->requester_id,
        ]);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit1->id,
            'status' => 'Tidak Aktif,Pending:BoD/BoC',
            'actor_id' => $ifsManager->id,
        ]);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit2->id,
            'condition' => 'Rusak Total',
            'actor_id' => $app2->requester_id,
        ]);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit2->id,
            'status' => 'Tidak Aktif,Pending:BoD/BoC',
            'actor_id' => $ifsManager->id,
        ]);
    }

    public function test_manager_can_bulk_reject_status(): void
    {
        $ifsManager = $this->createIfsManager();
        $unit1 = $this->createUnit();
        $unit2 = $this->createUnit();

        $unit1->update(['condition' => 'Hilang', 'status' => 'Tersedia,Pending:DM']);
        $app1 = AssetStatusApproval::create([
            'asset_id' => $unit1->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
            'lost_doc_url' => 'lost_docs/placeholder.pdf',
        ]);

        $unit2->update(['condition' => 'Rusak Total', 'status' => 'Tersedia,Pending:DM']);
        $app2 = AssetStatusApproval::create([
            'asset_id' => $unit2->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
            'lost_doc_url' => null,
        ]);

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-deactivation.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'rejected',
            'note' => 'Bulk reject reason',
        ]);

        $response->assertRedirect();
        
        $app1->refresh();
        $app2->refresh();
        $unit1->refresh();
        $unit2->refresh();

        $this->assertEquals('rejected', $app1->decision);
        $this->assertEquals('rejected', $app2->decision);
        
        $this->assertEquals('Tersedia', $unit1->status);
        $this->assertEquals('Bagus', $unit1->condition);
        $this->assertEquals('Tersedia', $unit2->status);
        $this->assertEquals('Bagus', $unit2->condition);

        $this->assertDatabaseHas('asset_status_approvals', [
            'id' => $app1->id,
            'decision' => 'rejected',
            'note' => 'Bulk reject reason',
            'approver_id' => $ifsManager->id,
        ]);
    }

    public function test_admin_can_upload_bod_boc_form_after_dm_approval_to_switch_status_to_dihapus(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $ifsManager = $this->createIfsManager();
        $unit = $this->createUnit();

        $unit->update(['condition' => 'Rusak Total', 'status' => 'Tersedia,Pending:DM']);
        $approval = AssetStatusApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
        ]);

        // Step 1: DM IFS approves -> status becomes Tidak Aktif,Pending:BoD/BoC, condition stays Rusak Total
        $this->actingAs($ifsManager)->post(route('smart.approve-deactivation.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'note' => 'Disetujui DM',
        ])->assertRedirect();

        $unit->refresh();
        $this->assertEquals('Tidak Aktif,Pending:BoD/BoC', $unit->status);
        $this->assertEquals('Rusak Total', $unit->condition);

        // Step 2: Admin uploads BoD/BoC approval form -> status becomes Dihapus, condition stays Rusak Total
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);
        $file = \Illuminate\Http\UploadedFile::fake()->create('bod_boc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->put(route('smart.inventory.assets.update', $unit), [
            'bod_boc_approval_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit->refresh();
        $approval->refresh();

        $this->assertEquals('Dihapus', $unit->status);
        $this->assertEquals('Rusak Total', $unit->condition);
        $this->assertNotNull($approval->bod_boc_approval_url);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit->id,
            'action_type' => 'Approval',
            'status' => 'Dihapus',
            'condition' => 'Rusak Total',
            'note' => 'BoD/BoC menyetujui penghapusan asset',
        ]);
    }
}
