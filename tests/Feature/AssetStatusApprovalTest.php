<?php

namespace Tests\Feature;

use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\UnitLifecycle;
use App\Models\AdmUser as User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetStatusApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable test bypass to ensure middleware checks roles
        config(['app.disable_test_admin_bypass' => true]);
    }

    private function createManager(): User
    {
        $managerUser = User::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);
        return $managerUser;
    }

    private function createUnit()
    {
        $lot = Lot::factory()->create();
        return Unit::create([
            'number' => $lot->number . '-U01',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'price' => $lot->unit_price,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);
    }

    public function test_only_manager_can_access_asset_status_approval_pages(): void
    {
        $user = User::factory()->create();
        $manager = $this->createManager();

        // Standard user -> 403
        $this->actingAs($user)->get(route('smart.approve-status'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.approve-status', ['history' => 'true']))->assertStatus(403);

        // Manager -> 200
        $this->actingAs($manager)->get(route('smart.approve-status'))->assertStatus(200);
        $this->actingAs($manager)->get(route('smart.approve-status', ['history' => 'true']))->assertStatus(200);
    }

    public function test_manager_can_bulk_approve_status(): void
    {
        $manager = $this->createManager();
        $unit1 = $this->createUnit();
        $unit2 = $this->createUnit();

        $app1 = UnitStatusApproval::create([
            'unit_id' => $unit1->id,
            'requester_id' => $manager->id,
            'proposed_status' => 'Perbaikan',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'doc_url' => 'memos/placeholder.pdf',
        ]);
        $unit1->update(['status' => 'Pending']);

        $app2 = UnitStatusApproval::create([
            'unit_id' => $unit2->id,
            'requester_id' => $manager->id,
            'proposed_status' => 'Rusak Total',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'doc_url' => 'memos/placeholder.pdf',
        ]);
        $unit2->update(['status' => 'Pending']);

        $response = $this->actingAs($manager)->post(route('smart.approve-status.bulk-store'), [
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
        
        $this->assertEquals('Dihapus', $unit1->status);
        $this->assertEquals('Dihapus', $unit2->status);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit1->id,
            'status' => 'Perbaikan',
            'actor_id' => $app1->requester_id,
        ]);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit1->id,
            'status' => 'Dihapus',
            'actor_id' => $manager->id,
            'note' => 'Bulk approval works',
        ]);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit2->id,
            'status' => 'Rusak Total',
            'actor_id' => $app2->requester_id,
        ]);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit2->id,
            'status' => 'Dihapus',
            'actor_id' => $manager->id,
            'note' => 'Bulk approval works',
        ]);
    }

    public function test_manager_can_bulk_reject_status(): void
    {
        $manager = $this->createManager();
        $unit1 = $this->createUnit();
        $unit2 = $this->createUnit();

        $app1 = UnitStatusApproval::create([
            'unit_id' => $unit1->id,
            'requester_id' => $manager->id,
            'proposed_status' => 'Perbaikan',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'doc_url' => 'memos/placeholder.pdf',
        ]);
        $unit1->update(['status' => 'Pending']);

        $app2 = UnitStatusApproval::create([
            'unit_id' => $unit2->id,
            'requester_id' => $manager->id,
            'proposed_status' => 'Rusak Total',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'doc_url' => 'memos/placeholder.pdf',
        ]);
        $unit2->update(['status' => 'Pending']);

        $response = $this->actingAs($manager)->post(route('smart.approve-status.bulk-store'), [
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
        
        $this->assertEquals('Tersedia', $unit1->status); // unchanged
        $this->assertEquals('Tersedia', $unit2->status); // unchanged

        $this->assertDatabaseHas('unit_status_approvals', [
            'id' => $app1->id,
            'decision' => 'rejected',
            'note' => 'Bulk reject reason',
            'approver_id' => $manager->id,
        ]);
    }
}
