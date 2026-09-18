<?php

namespace Tests\Feature;

use App\Models\User;
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
use App\Models\TbProject;
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

    private function createAdmin(): User
    {
        $admin = User::where('username', '255578')->first();
        if ($admin) {
            return $admin;
        }
        $employee = HrdEmployee::firstOrCreate(['employee_id' => '255578']);
        return User::factory()->create(['employee_id' => $employee->employee_id, 'username' => '255578']);
    }

    private function createBorrower(): User
    {
        $borrower = User::factory()->create([
            'name' => 'Budi Santoso',
        ]);
        $borrower->load('hrdEmployee');
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
                'utilization' => 'corporate',
                'org_id' => $borrower->hrdEmployee->orgchart_id,
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
        $this->assertEquals('corporate', $smartReq->utilization);
        $this->assertEquals($borrower->hrdEmployee->orgchart_id, $smartReq->org_id);
        $this->assertNull($smartReq->project_id);
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
        $this->assertEquals('corporate', $activeBorrowing['utilization']);
        $this->assertEquals($borrower->hrdEmployee->orgchart_id, $activeBorrowing['org_id']);
        $this->assertNull($activeBorrowing['project_id']);
        $this->assertEquals($smartReq->request_number, $activeBorrowing['request_number']);
    }

    public function test_admin_can_start_manual_borrowing_for_project(): void
    {
        $admin = $this->createAdmin();
        $borrower = $this->createBorrower();
        $unit = $this->createAvailableUnit();
        $project = TbProject::factory()->create(['no_project' => 'PRJ-SMART-01', 'project_name' => 'SMART Overhaul']);

        $startDate = Carbon::today()->toDateString();
        $note = 'Keperluan proyek overhaul.';

        $response = $this->actingAs($admin)
            ->from('/smart/inventory/assets')
            ->post(route('smart.inventory.units.borrow', $unit->id), [
                'user_id' => $borrower->id,
                'start_date' => $startDate,
                'utilization' => 'project',
                'project_id' => $project->id_project,
                'note' => $note,
            ]);

        $response->assertRedirect('/smart/inventory/assets');
        $response->assertSessionHas('success');

        $unit->refresh();
        $this->assertEquals('Dipinjam', $unit->status);

        $smartReq = SmartRequest::where('user_id', $borrower->id)->latest('id')->first();
        $this->assertNotNull($smartReq);
        $this->assertEquals('project', $smartReq->utilization);
        $this->assertEquals($project->id_project, $smartReq->project_id);
        $this->assertNull($smartReq->org_id);

        $activeBorrowing = $unit->active_borrowing;
        $this->assertNotNull($activeBorrowing);
        $this->assertEquals('project', $activeBorrowing['utilization']);
        $this->assertEquals($project->id_project, $activeBorrowing['project_id']);
        $this->assertNull($activeBorrowing['org_id']);
    }

    public function test_borrow_validation_requires_utilization_and_targets(): void
    {
        $admin = $this->createAdmin();
        $borrower = $this->createBorrower();
        $unit = $this->createAvailableUnit();

        // 1. Missing utilization
        $response = $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower->id,
            'start_date' => Carbon::today()->toDateString(),
        ]);
        $response->assertSessionHasErrors(['utilization']);

        // 2. Corporate without org_id
        $response = $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower->id,
            'start_date' => Carbon::today()->toDateString(),
            'utilization' => 'corporate',
        ]);
        $response->assertSessionHasErrors(['org_id']);

        // 3. Project without project_id
        $response = $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower->id,
            'start_date' => Carbon::today()->toDateString(),
            'utilization' => 'project',
        ]);
        $response->assertSessionHasErrors(['project_id']);
    }

    public function test_admin_can_update_active_borrowing(): void
    {
        $admin = $this->createAdmin();
        $borrower1 = $this->createBorrower();
        $borrower2Org = HrdOrgchart::factory()->create(['org_name' => 'Finance', 'org_code' => 'FIN']);
        $borrower2 = User::factory()->create([
            'name' => 'Siti Nurhaliza',
        ]);
        $borrower2->load('hrdEmployee');
        $borrower2->hrdEmployee->update(['orgchart_id' => $borrower2Org->id]);
        $unit = $this->createAvailableUnit();

        $startDate = Carbon::today()->toDateString();

        // 1. Initial borrow
        $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower1->id,
            'start_date' => $startDate,
            'utilization' => 'corporate',
            'org_id' => $borrower1->hrdEmployee->orgchart_id,
            'note' => 'Initial note',
        ]);

        // 2. Update to borrower 2 with updated date
        $newDate = Carbon::today()->addDay()->toDateString();
        $newNote = 'Updated note';

        $this->actingAs($admin)->post(route('smart.inventory.units.borrow', $unit->id), [
            'user_id' => $borrower2->id,
            'start_date' => $newDate,
            'utilization' => 'corporate',
            'org_id' => $borrower2Org->id,
            'note' => $newNote,
        ]);

        $fulfillment = RequestFulfillment::where('unit_id', $unit->id)->whereNull('completed_at')->first();
        $this->assertEquals(Carbon::parse($newDate)->startOfDay()->format('Y-m-d'), $fulfillment->assigned_at->format('Y-m-d'));
        $this->assertEquals(Carbon::parse($newDate)->startOfDay()->format('Y-m-d'), $fulfillment->confirmed_at->format('Y-m-d'));

        $unit->refresh();
        $this->assertEquals($borrower2->id, $unit->active_borrowing['user_id']);
        $this->assertEquals('corporate', $unit->active_borrowing['utilization']);
        $this->assertEquals($borrower2Org->id, $unit->active_borrowing['org_id']);
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
            'utilization' => 'corporate',
            'org_id' => $borrower->hrdEmployee->orgchart_id,
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
