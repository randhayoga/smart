<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitLifecycle;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Subcategory;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for UnitBorrowController manual borrowing workflows.
 */
class UnitBorrowControllerTest extends TestCase
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

    private function createAdmin(): AdmUser
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        return AdmUser::factory()->create(['employee_id' => $employee->employee_id]);
    }

    private function createBorrower(): AdmUser
    {
        $borrower = AdmUser::factory()->create([
            'name' => 'Budi Santoso',
        ]);

        return $borrower;
    }

    private function createAvailableUnit(): Unit
    {
        $location = Location::create(['name' => 'Gudang Utama', 'full_name' => 'Gudang Utama']);
        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id, 'location_id' => $location->id]);

        return Unit::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'number' => 'AST-TEST-001',
        ]);
    }

    public function test_users_endpoint_returns_json_options(): void
    {
        $admin = $this->createAdmin();
        $borrower = $this->createBorrower();

        $response = $this->actingAs($admin)->getJson(route('smart.inventory.users'));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $borrower->id,
            'name' => "{$borrower->name} ({$borrower->employee_id})",
        ]);
    }

    public function test_admin_can_start_manual_borrowing_with_assigned_and_confirmed_timestamps(): void
    {
        $admin = $this->createAdmin();
        $borrower = $this->createBorrower();
        $unit = $this->createAvailableUnit();

        $startDate = Carbon::today()->toDateString();
        $note = 'Keperluan dinas luar kota.';

        $response = $this->actingAs($admin)
            ->from('/smart/inventory/assets')
            ->post(route('smart.inventory.units.borrow', $unit->id), [
                'user_id' => $borrower->id,
                'start_date' => $startDate,
                'note' => $note,
            ]);

        $response->assertRedirect('/smart/inventory/assets');
        $response->assertSessionHas('success');

        // Unit status changed to Dipinjam
        $unit->refresh();
        $this->assertEquals('Dipinjam', $unit->status);

        // SmartRequest created
        $smartReq = SmartRequest::where('user_id', $borrower->id)->latest('id')->first();
        $this->assertNotNull($smartReq);
        $this->assertEquals('borrow', $smartReq->status);
        $this->assertEquals($admin->id, $smartReq->approver_id);
        $this->assertEquals($note, $smartReq->reasoning);

        // RequestFulfillment created with both assigned_at AND confirmed_at populated, completed_at NULL
        $fulfillment = RequestFulfillment::where('unit_id', $unit->id)
            ->whereNull('completed_at')
            ->first();

        $this->assertNotNull($fulfillment);
        $this->assertEquals(Carbon::parse($startDate)->startOfDay()->format('Y-m-d'), $fulfillment->assigned_at->format('Y-m-d'));
        $this->assertEquals(Carbon::parse($startDate)->startOfDay()->format('Y-m-d'), $fulfillment->confirmed_at->format('Y-m-d'));
        $this->assertNull($fulfillment->completed_at);

        // Active borrowing accessor on Unit model returns details
        $activeBorrowing = $unit->active_borrowing;
        $this->assertNotNull($activeBorrowing);
        $this->assertEquals($borrower->id, $activeBorrowing['user_id']);
        $this->assertEquals($smartReq->request_number, $activeBorrowing['request_number']);
    }

    public function test_admin_can_update_active_borrowing(): void
    {
        $admin = $this->createAdmin();
        $borrower1 = $this->createBorrower();
        $borrower2 = AdmUser::factory()->create(['name' => 'Siti Nurhaliza']);
        $unit = $this->createAvailableUnit();

        $startDate = Carbon::today()->toDateString();

        // 1. Initial borrow
        $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower1->id,
            'start_date' => $startDate,
            'note' => 'Initial note',
        ]);

        // 2. Update to borrower 2 with updated date
        $newDate = Carbon::today()->addDay()->toDateString();
        $newNote = 'Updated note';

        $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower2->id,
            'start_date' => $newDate,
            'note' => $newNote,
        ]);

        $fulfillment = RequestFulfillment::where('unit_id', $unit->id)->whereNull('completed_at')->first();
        $this->assertEquals(Carbon::parse($newDate)->startOfDay()->format('Y-m-d'), $fulfillment->assigned_at->format('Y-m-d'));
        $this->assertEquals(Carbon::parse($newDate)->startOfDay()->format('Y-m-d'), $fulfillment->confirmed_at->format('Y-m-d'));

        $unit->refresh();
        $this->assertEquals($borrower2->id, $unit->active_borrowing['user_id']);
        $this->assertEquals($newNote, $unit->active_borrowing['note']);
    }

    public function test_admin_can_finish_borrowing_and_restore_unit_status(): void
    {
        $admin = $this->createAdmin();
        $borrower = $this->createBorrower();
        $unit = $this->createAvailableUnit();

        $startDate = Carbon::today()->toDateString();

        // 1. Start borrow
        $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower->id,
            'start_date' => $startDate,
            'note' => 'Dipinjam untuk proyek audit',
        ]);

        // 2. Finish borrow
        $finishResponse = $this->actingAs($admin)
            ->from('/smart/inventory/assets')
            ->post(route('smart.inventory.units.finish-borrow', $unit->id));

        $finishResponse->assertRedirect('/smart/inventory/assets');
        $finishResponse->assertSessionHas('success');

        // Unit status restored to Tersedia
        $unit->refresh();
        $this->assertEquals('Tersedia', $unit->status);
        $this->assertNull($unit->active_borrowing);

        // Fulfillment marked as completed
        $fulfillment = RequestFulfillment::where('unit_id', $unit->id)->first();
        $this->assertNotNull($fulfillment->completed_at);

        // SmartRequest marked as success
        $smartReq = SmartRequest::where('user_id', $borrower->id)->first();
        $this->assertEquals('success', $smartReq->status);

        // Lifecycle audit trails created (Initial Registrasi + Peminjaman & Pengembalian)
        $lifecycles = UnitLifecycle::where('unit_id', $unit->id)->get();
        $this->assertGreaterThanOrEqual(2, $lifecycles->count());

        $borrowLog = $lifecycles->firstWhere('action_type', 'Peminjaman');
        $this->assertNotNull($borrowLog);
        $this->assertEquals('Dipinjam', $borrowLog->status);
        $this->assertNotNull($borrowLog->end_date);

        $returnLog = $lifecycles->firstWhere('action_type', 'Pengembalian');
        $this->assertNotNull($returnLog);
        $this->assertEquals('Tersedia', $returnLog->status);
        $this->assertNull($returnLog->end_date);
    }
}
