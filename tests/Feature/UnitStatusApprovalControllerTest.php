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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitStatusApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createManager(): User
    {
        $managerUser = User::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        if (!$employee) {
            $orgchart = HrdOrgchart::factory()->create(['employee_id' => $managerUser->employee_id]);
            $employee = HrdEmployee::factory()->create([
                'employee_id' => $managerUser->employee_id,
                'orgchart_id' => $orgchart->id,
            ]);
        } else {
            $orgchart = HrdOrgchart::find($employee->orgchart_id);
            if ($orgchart) {
                $orgchart->update(['employee_id' => $managerUser->employee_id]);
            }
        }
        return $managerUser;
    }

    private function createUnit()
    {
        $lot = Lot::factory()->create();
        
        $tipeCode = $lot->barang->subcategory->code ?? 'SUB';
        $organizerCode = $lot->organizer->name ?? 'ORG';
        $combination = "{$tipeCode}-{$organizerCode}-PTRE";
        $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');
        $unitNumber = "00001-{$combination}-{$yy}";
        if (strlen($unitNumber) > 25) {
            $unitNumber = substr($unitNumber, 0, 25);
        }

        return Unit::create([
            'number' => $unitNumber,
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => $lot->unit_price,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);
    }

    public function test_can_store_unit_status_approval_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $response = $this->actingAs($user)->post(route('smart.inventory.unit-status-approvals.store'), [
            'unit_id' => $unit->id,
            'proposed_condition' => 'Rusak Total',
            'note' => 'Butuh penggantian unit',
            'memo_file' => \Illuminate\Http\UploadedFile::fake()->create('memo.pdf', 100),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengajuan perubahan status unit berhasil dikirim.');

        $this->assertDatabaseHas('unit_status_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'note' => 'Butuh penggantian unit',
        ]);

        $unit->refresh();
        $this->assertEquals('Pending:BoD/BoC', $unit->status);
    }

    public function test_cannot_store_duplicate_pending_request(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'requested_at' => now(),
            'memo_url' => 'memos/placeholder.pdf',
            'lost_doc_url' => null,
        ]);

        $response = $this->actingAs($user)->post(route('smart.inventory.unit-status-approvals.store'), [
            'unit_id' => $unit->id,
            'proposed_condition' => 'Hilang',
            'memo_file' => \Illuminate\Http\UploadedFile::fake()->create('memo.pdf', 100),
            'lost_doc_file' => \Illuminate\Http\UploadedFile::fake()->create('lost.pdf', 100),
        ]);

        $response->assertSessionHasErrors(['unit_id']);
        $this->assertEquals(1, UnitStatusApproval::count());
    }

    public function test_storing_unit_with_status_rusak_creates_pending_approval(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $location = \App\Models\Master\Location::factory()->create();
        $file = \Illuminate\Http\UploadedFile::fake()->image('unit.jpg');

        $tipeCode = $lot->barang->subcategory->code ?? 'SUB';
        $organizerCode = $lot->organizer->name ?? 'ORG';
        $combination = "{$tipeCode}-{$organizerCode}-PTRE";
        $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');
        $unitNumber = "00099-{$combination}-{$yy}";
        if (strlen($unitNumber) > 25) {
            $unitNumber = substr($unitNumber, 0, 25);
        }

        $response = $this->actingAs($user)->post(route('smart.inventory.units.store'), [
            'number' => $unitNumber,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'price' => 50000,
            'image_url' => $file,
            'use_lot_image' => false,
            'memo_file' => \Illuminate\Http\UploadedFile::fake()->create('memo.pdf', 100),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $unit = Unit::where('lot_id', $lot->id)->firstOrFail();
        $this->assertEquals('Pending:BoD/BoC', $unit->status);

        $this->assertDatabaseHas('unit_status_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
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
            'status' => 'Tersedia',
            'condition' => 'Rusak Total',
            'price' => $unit->price,
            'memo_file' => \Illuminate\Http\UploadedFile::fake()->create('memo.pdf', 100),
        ]);

        $response->assertRedirect();
        
        $unit->refresh();
        $this->assertEquals('Pending:BoD/BoC', $unit->status);

        $this->assertDatabaseHas('unit_status_approvals', [
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'pending',
            'approver_id' => null,
            'note' => null,
        ]);
    }

    public function test_multi_action_update_splits_audit_trail_entries(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();
        $newLocation = \App\Models\Master\Location::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.inventory.units.update', $unit), [
            'number' => $unit->number,
            'lot_id' => $unit->lot_id,
            'location_id' => $newLocation->id,
            'status' => 'Standby',
            'condition' => 'Rusak',
            'price' => $unit->price,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $lifecycles = UnitLifecycle::where('unit_id', $unit->id)
            ->where('action_type', '!=', 'Registrasi')
            ->get();

        $this->assertCount(3, $lifecycles);

        $actionTypes = $lifecycles->pluck('action_type')->toArray();
        $this->assertContains('Perubahan status', $actionTypes);
        $this->assertContains('Perubahan kondisi', $actionTypes);
        $this->assertContains('Pemindahan', $actionTypes);
    }
}
