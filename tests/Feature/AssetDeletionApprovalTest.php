<?php

namespace Tests\Feature;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Models\User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Master\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Asset Deletion Approval Feature Tests
 *
 * Verifies role-based access control and batch approve/reject actions for asset condition/deletion requests.
 */
class AssetDeletionApprovalTest extends TestCase
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

    private static int $unitCounter = 1;

    private function createUnit(): Asset
    {
        $location = Location::factory()->create();
        $lot = Lot::factory()->create(['location_id' => $location->id]);
        
        $tipeCode = $lot->barang->subcategory->code ?? 'SUB';
        $organizerCode = $lot->organizer->name ?? 'ORG';
        $combination = "{$tipeCode}-{$organizerCode}-PTRE";
        $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');
        $seq = str_pad((string) (self::$unitCounter++), 5, '0', STR_PAD_LEFT);
        $unitNumber = "{$seq}-{$combination}-{$yy}";
        if (strlen($unitNumber) > 25) {
            $unitNumber = substr($unitNumber, 0, 25);
        }

        return Asset::create([
            'number' => $unitNumber,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
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
        $this->actingAs($user)->get(route('smart.approve-deletion'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.approve-deletion', ['history' => 'true']))->assertStatus(403);

        // Admin -> 403 (Admin is requester, not approval authority)
        $this->actingAs($admin)->get(route('smart.approve-deletion'))->assertStatus(403);
        $this->actingAs($admin)->get(route('smart.approve-deletion', ['history' => 'true']))->assertStatus(403);

        // Department Manager -> 403 (General manager only approves requisitions, not asset lifecycle)
        $this->actingAs($deptManager)->get(route('smart.approve-deletion'))->assertStatus(403);
        $this->actingAs($deptManager)->get(route('smart.approve-deletion', ['history' => 'true']))->assertStatus(403);

        // IFS Manager -> 200
        $this->actingAs($ifsManager)->get(route('smart.approve-deletion'))->assertStatus(200);
        $this->actingAs($ifsManager)->get(route('smart.approve-deletion', ['history' => 'true']))->assertStatus(200);
    }

    public function test_manager_can_bulk_approve_status(): void
    {
        $ifsManager = $this->createIfsManager();
        $admin = $this->createAdmin();
        $deptManager = $this->createDepartmentManager();
        $unit1 = $this->createUnit();
        $unit2 = $this->createUnit();

        $unit1->update(['condition' => 'Hilang', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $app1 = AssetDeletionApproval::create([
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

        $unit2->update(['condition' => 'Rusak Total', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $app2 = AssetDeletionApproval::create([
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
        $this->actingAs($admin)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'approved',
            'dm_note' => 'Unauthorized admin attempt',
        ])->assertStatus(403);

        $this->actingAs($deptManager)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'approved',
            'dm_note' => 'Unauthorized manager attempt',
        ])->assertStatus(403);

        // Authorized IFS Manager can perform bulk approval
        $response = $this->actingAs($ifsManager)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'approved',
            'dm_note' => 'Bulk approval works',
        ]);

        $response->assertRedirect();
        
        $app1->refresh();
        $app2->refresh();
        $unit1->refresh();
        $unit2->refresh();

        $this->assertEquals('approved', $app1->decision);
        $this->assertEquals('approved', $app2->decision);
        $this->assertEquals('Bulk approval works', $app1->dm_note);
        $this->assertEquals('Bulk approval works', $app2->dm_note);
        
        $this->assertEquals('Tidak Aktif', $unit1->status);
        $this->assertEquals('Pending:BoD/BoC', $unit1->approval_status);
        $this->assertEquals('Hilang', $unit1->condition);

        $this->assertEquals('Tidak Aktif', $unit2->status);
        $this->assertEquals('Pending:BoD/BoC', $unit2->approval_status);
        $this->assertEquals('Rusak Total', $unit2->condition);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit1->id,
            'condition' => 'Hilang',
            'actor_id' => $app1->requester_id,
        ]);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit1->id,
            'status' => 'Tidak Aktif',
            'actor_id' => $ifsManager->id,
        ]);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit2->id,
            'condition' => 'Rusak Total',
            'actor_id' => $app2->requester_id,
        ]);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit2->id,
            'status' => 'Tidak Aktif',
            'actor_id' => $ifsManager->id,
        ]);
    }

    public function test_manager_can_bulk_reject_status(): void
    {
        $ifsManager = $this->createIfsManager();
        $unit1 = $this->createUnit();
        $unit2 = $this->createUnit();

        $unit1->update(['condition' => 'Hilang', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $app1 = AssetDeletionApproval::create([
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

        $unit2->update(['condition' => 'Rusak Total', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $app2 = AssetDeletionApproval::create([
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

        $response = $this->actingAs($ifsManager)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$app1->id, $app2->id],
            'decision' => 'rejected',
            'dm_note' => 'Bulk reject reason',
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

        $this->assertDatabaseHas('asset_deletion_approvals', [
            'id' => $app1->id,
            'decision' => 'rejected',
            'dm_note' => 'Bulk reject reason',
            'approver_id' => $ifsManager->id,
        ]);
    }

    public function test_admin_can_upload_bod_boc_form_after_dm_approval_to_switch_status_to_dihapus(): void
    {
        Storage::fake('local');
        $ifsManager = $this->createIfsManager();
        $unit = $this->createUnit();

        $unit->update(['condition' => 'Rusak Total', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $approval = AssetDeletionApproval::create([
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
        $this->actingAs($ifsManager)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'dm_note' => 'Disetujui DM',
        ])->assertRedirect();

        $unit->refresh();
        $this->assertEquals('Tidak Aktif', $unit->status);
        $this->assertEquals('Pending:BoD/BoC', $unit->approval_status);
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
            'action_type' => 'Persetujuan',
            'status' => 'Dihapus',
            'condition' => 'Rusak Total',
            'note' => 'BoD/BoC menyetujui penghapusan asset dengan kondisi Rusak Total',
        ]);
    }

    public function test_admin_can_upload_bod_boc_disapproval_form_to_revert_status_and_condition(): void
    {
        Storage::fake('local');
        $ifsManager = $this->createIfsManager();
        $unit = $this->createUnit();

        $unit->update(['condition' => 'Hilang', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $approval = AssetDeletionApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
        ]);

        // Step 1: DM IFS approves -> status becomes Tidak Aktif,Pending:BoD/BoC, condition stays Hilang
        $this->actingAs($ifsManager)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$approval->id],
            'decision' => 'approved',
            'dm_note' => 'Disetujui DM',
        ])->assertRedirect();

        $unit->refresh();
        $this->assertEquals('Tidak Aktif', $unit->status);
        $this->assertEquals('Pending:BoD/BoC', $unit->approval_status);

        // Step 2: Admin uploads BoD/BoC disapproval form -> status reverts to Tersedia, condition to Bagus
        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);
        $file = \Illuminate\Http\UploadedFile::fake()->create('bod_boc_disapproval.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->put(route('smart.inventory.assets.update', $unit), [
            'bod_boc_disapproval_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit->refresh();
        $approval->refresh();

        $this->assertEquals('Tersedia', $unit->status);
        $this->assertEquals('Bagus', $unit->condition);
        $this->assertNotNull($approval->bod_boc_disapproval_url);
        $this->assertEquals('rejected', $approval->decision);

        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $unit->id,
            'action_type' => 'Penolakan',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'note' => 'BoD/BoC menolak penghapusan asset dengan kondisi Hilang',
        ]);
    }

    public function test_controller_rejects_uploading_both_approval_and_disapproval_documents(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $unit->update(['condition' => 'Rusak Total', 'status' => 'Tidak Aktif', 'approval_status' => 'Pending:BoD/BoC']);

        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        $approvalFile = \Illuminate\Http\UploadedFile::fake()->create('approval.pdf', 100, 'application/pdf');
        $disapprovalFile = \Illuminate\Http\UploadedFile::fake()->create('disapproval.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->put(route('smart.inventory.assets.update', $unit), [
            'bod_boc_approval_file' => $approvalFile,
            'bod_boc_disapproval_file' => $disapprovalFile,
        ]);

        $response->assertSessionHasErrors(['bod_boc_approval_file', 'bod_boc_disapproval_file']);
    }

    public function test_successive_bod_boc_disapproval_overwrites_previous_document_and_media_is_accessible(): void
    {
        Storage::fake('local');
        $ifsManager = $this->createIfsManager();
        $unit = $this->createUnit();

        // --- Cycle 1 ---
        $unit->update(['condition' => 'Hilang', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $approval1 = AssetDeletionApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Hilang',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'approved',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder1.pdf',
        ]);
        $unit->update(['status' => 'Tidak Aktif', 'approval_status' => 'Pending:BoD/BoC']);

        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $admin = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);
        $file1 = \Illuminate\Http\UploadedFile::fake()->create('bod_boc_disapproval_1.pdf', 100, 'application/pdf');

        $this->actingAs($admin)->put(route('smart.inventory.assets.update', $unit), [
            'bod_boc_disapproval_file' => $file1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $unit->refresh();
        $approval1->refresh();

        $this->assertEquals('Tersedia', $unit->status);
        $this->assertEquals('rejected', $approval1->decision);
        $file1Path = $approval1->bod_boc_disapproval_url;
        $this->assertNotNull($file1Path);
        Storage::disk('local')->assertExists($file1Path);

        // Verify media route returns 200 (not 404)
        $this->actingAs($admin)->get("/media/{$file1Path}")->assertOk();

        // Verify NonConsumableController returns bod_boc_disapproval_url even when asset status is Tersedia
        $response = $this->actingAs($admin)->get('/smart/stock-management/non-consumables');
        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $returnedUnit = collect($props['units'])->firstWhere('id', $unit->id);
        $this->assertNotNull($returnedUnit);
        $this->assertEquals($file1Path, $returnedUnit['bod_boc_disapproval_url']);

        // --- Cycle 2 (Successive Disapproval) ---
        $unit->update(['condition' => 'Rusak Total', 'status' => 'Tersedia', 'approval_status' => 'Pending:DM']);
        $approval2 = AssetDeletionApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'approved',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder2.pdf',
        ]);
        $unit->update(['status' => 'Tidak Aktif', 'approval_status' => 'Pending:BoD/BoC']);

        $file2 = \Illuminate\Http\UploadedFile::fake()->create('bod_boc_disapproval_2.pdf', 100, 'application/pdf');

        $this->actingAs($admin)->put(route('smart.inventory.assets.update', $unit), [
            'bod_boc_disapproval_file' => $file2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $unit->refresh();
        $approval1->refresh();
        $approval2->refresh();

        $this->assertEquals('Tersedia', $unit->status);
        $this->assertEquals('rejected', $approval2->decision);
        $file2Path = $approval2->bod_boc_disapproval_url;
        $this->assertNotNull($file2Path);
        $this->assertNotEquals($file1Path, $file2Path);

        // Verify file1 was deleted and file2 exists
        Storage::disk('local')->assertMissing($file1Path);
        Storage::disk('local')->assertExists($file2Path);

        // Verify old approval record had its bod_boc_disapproval_url cleared
        $this->assertNull($approval1->bod_boc_disapproval_url);

        // Verify media route for file2 returns 200
        $this->actingAs($admin)->get("/media/{$file2Path}")->assertOk();

        // Verify NonConsumableController returns the newest file2Path
        $response2 = $this->actingAs($admin)->get('/smart/stock-management/non-consumables');
        $props2 = $response2->original->getData()['page']['props'];
        $returnedUnit2 = collect($props2['units'])->firstWhere('id', $unit->id);
        $this->assertEquals($file2Path, $returnedUnit2['bod_boc_disapproval_url']);
    }

    public function test_dm_approval_immediately_deletes_previous_bod_boc_disapproval_file_and_clears_url(): void
    {
        Storage::fake('local');
        $ifsManager = $this->createIfsManager();
        $unit = $this->createUnit();

        // 1. Previous approval with BoD/BoC disapproval file
        $disapprovalPath = 'bod_boc_disapprovals/disapproval.pdf';
        Storage::disk('local')->put($disapprovalPath, 'dummy disapproval content');

        $prevApproval = AssetDeletionApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'rejected',
            'requested_at' => now()->subDays(2),
            'bod_boc_disapproval_url' => $disapprovalPath,
        ]);

        Storage::disk('local')->assertExists($disapprovalPath);

        // 2. New pending approval request submitted
        $newApproval = AssetDeletionApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/memo.pdf',
        ]);
        $unit->update(['status' => 'Tersedia', 'approval_status' => 'Pending:DM']);

        // 3. IFS Manager approves the deletion
        $this->actingAs($ifsManager)->post(route('smart.approve-deletion.bulk-store'), [
            'ids' => [$newApproval->id],
            'decision' => 'approved',
            'dm_note' => 'Approved again by DM',
        ])->assertRedirect();

        // 4. Verify previous disapproval file was deleted immediately from disk
        Storage::disk('local')->assertMissing($disapprovalPath);

        // 5. Verify previous approval record had bod_boc_disapproval_url cleared
        $prevApproval->refresh();
        $this->assertNull($prevApproval->bod_boc_disapproval_url);

        // 6. Verify unit status updated to Tidak Aktif,Pending:BoD/BoC
        $unit->refresh();
        $this->assertEquals('Tidak Aktif', $unit->status);
        $this->assertEquals('Pending:BoD/BoC', $unit->approval_status);
    }

    public function test_approve_deletion_datatable_data_has_no_blank_fields(): void
    {
        $ifsManager = $this->createIfsManager();
        $unit = $this->createUnit();
        $unit->update(['status' => 'Tersedia', 'approval_status' => 'Pending:DM', 'condition' => 'Rusak Total']);

        $pendingApproval = AssetDeletionApproval::create([
            'asset_id' => $unit->id,
            'requester_id' => $ifsManager->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/memo.pdf',
        ]);

        // 1. Pending table view
        $response = $this->actingAs($ifsManager)->get(route('smart.approve-deletion'));
        $response->assertOk();
        $approvals = $response->viewData('page')['props']['approvals'];
        $this->assertNotEmpty($approvals);
        $item = $approvals[0];

        $this->assertEquals($unit->number, $item['asset_code']);
        $this->assertEquals($unit->number, $item['asset_number']);
        $this->assertNotEmpty($item['category']);
        $this->assertNotEquals('-', $item['category']);
        $this->assertNotEmpty($item['subcategory']);
        $this->assertNotEquals('-', $item['subcategory']);
        $this->assertNotEmpty($item['brand']);
        $this->assertNotEquals('-', $item['brand']);
        $this->assertNotEmpty($item['nama']);
        $this->assertNotEquals('-', $item['nama']);
        $this->assertNotEmpty($item['name']);
        $this->assertEquals($item['nama'], $item['name']);
        $this->assertEquals('Rusak Total', $item['status_label']);
        $this->assertIsArray($item['asset_details']);
        $this->assertIsArray($item['unit_details']);
        $this->assertEquals($unit->number, $item['asset_details']['number']);
        $this->assertEquals($unit->number, $item['unit_details']['number']);

        // 2. Processed / History table view
        $pendingApproval->update([
            'decision' => 'approved',
            'approver_id' => $ifsManager->id,
            'decided_at' => now(),
            'dm_note' => 'Approved',
        ]);

        $histResponse = $this->actingAs($ifsManager)->get(route('smart.approve-deletion', ['history' => 'true']));
        $histResponse->assertOk();
        $histApprovals = $histResponse->viewData('page')['props']['approvals'];
        $this->assertNotEmpty($histApprovals);
        $histItem = $histApprovals[0];

        $this->assertEquals($unit->number, $histItem['asset_code']);
        $this->assertEquals($unit->number, $histItem['asset_number']);
        $this->assertNotEmpty($histItem['nama']);
        $this->assertEquals('approved', $histItem['decision']);
        $this->assertEquals($ifsManager->name, $histItem['approver_name']);
    }
}
