<?php

namespace Tests\Feature;

use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\UnitLifecycle;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitStatusApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUnit()
    {
        $lot = Lot::factory()->create();
        return Unit::create([
            'number' => $lot->number . '-U01',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'available',
            'condition' => 'Baik',
            'price' => $lot->unit_price,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);
    }

    public function test_can_list_unit_status_approval_requests(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('smart.inventory.unit-status-approvals.index'));

        $response->assertStatus(200);
    }

    public function test_can_store_unit_status_approval_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $response = $this->actingAs($user)->post(route('smart.inventory.unit-status-approvals.store'), [
            'unit_id' => $unit->id,
            'proposed_status' => 'maintenance',
            'note' => 'Butuh perbaikan rutin',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengajuan perubahan status unit berhasil dikirim.');

        $this->assertDatabaseHas('unit_status_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'note' => 'Butuh perbaikan rutin',
        ]);
    }

    public function test_cannot_store_duplicate_pending_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('smart.inventory.unit-status-approvals.store'), [
            'unit_id' => $unit->id,
            'proposed_status' => 'broken',
        ]);

        $response->assertSessionHasErrors(['unit_id']);
        $this->assertEquals(1, UnitStatusApproval::count());
    }

    public function test_can_show_unit_status_approval_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('smart.inventory.unit-status-approvals.show', $approval));

        $response->assertStatus(200);
    }

    public function test_can_approve_unit_status_approval_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('smart.inventory.unit-status-approvals.update', $approval), [
            'decision' => 'approved',
            'note' => 'Disetujui untuk maintenance',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengajuan perubahan status unit disetujui.');

        $this->assertDatabaseHas('unit_status_approvals', [
            'id' => $approval->id,
            'decision' => 'approved',
            'approver_id' => $user->id,
            'note' => 'Disetujui untuk maintenance',
        ]);

        $unit->refresh();
        $this->assertEquals('maintenance', $unit->status);

        $this->assertDatabaseHas('unit_lifecycles', [
            'unit_id' => $unit->id,
            'status' => 'maintenance',
            'requester_id' => $approval->requester_id,
            'approver_id' => $user->id,
            'note' => 'Disetujui untuk maintenance',
        ]);
    }

    public function test_can_reject_unit_status_approval_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('smart.inventory.unit-status-approvals.update', $approval), [
            'decision' => 'rejected',
            'note' => 'Alasan penolakan',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengajuan perubahan status unit ditolak.');

        $this->assertDatabaseHas('unit_status_approvals', [
            'id' => $approval->id,
            'decision' => 'rejected',
            'approver_id' => $user->id,
            'note' => 'Alasan penolakan',
        ]);

        $unit->refresh();
        $this->assertEquals('available', $unit->status); // unchanged
    }

    public function test_can_destroy_pending_unit_status_approval_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'maintenance',
            'decision' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('smart.inventory.unit-status-approvals.destroy', $approval));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengajuan perubahan status unit berhasil dibatalkan.');
        $this->assertDatabaseMissing('unit_status_approvals', ['id' => $approval->id]);
    }

    public function test_storing_unit_with_status_rusak_creates_pending_approval(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $location = \App\Models\Master\Location::factory()->create();
        $file = \Illuminate\Http\UploadedFile::fake()->image('unit.jpg');

        $response = $this->actingAs($user)->post(route('smart.inventory.units.store'), [
            'number' => $lot->number . '-U99',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'rusak',
            'condition' => 'Rusak',
            'price' => 50000,
            'image_url' => $file,
            'use_lot_image' => false,
        ]);

        $response->assertRedirect();
        
        $unit = Unit::where('number', $lot->number . '-U99')->firstOrFail();
        $this->assertEquals('tersedia', $unit->status);

        $this->assertDatabaseHas('unit_status_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'rusak',
            'decision' => 'pending',
            'approver_id' => null,
            'note' => null,
        ]);
    }

    public function test_updating_unit_with_status_rusak_creates_pending_approval(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $response = $this->actingAs($user)->put(route('smart.inventory.units.update', $unit), [
            'number' => $unit->number,
            'lot_id' => $unit->lot_id,
            'location_id' => $unit->location_id,
            'status' => 'rusak',
            'condition' => 'Rusak',
            'price' => $unit->price,
        ]);

        $response->assertRedirect();
        
        $unit->refresh();
        $this->assertEquals('available', $unit->status);

        $this->assertDatabaseHas('unit_status_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_status' => 'rusak',
            'decision' => 'pending',
            'approver_id' => null,
            'note' => null,
        ]);
    }
}
