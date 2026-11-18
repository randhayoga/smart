<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\AssetLocationAssignment;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetAssignedToLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $pic;
    private Lot $lot;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->pic = User::factory()->create([
            'employee_id' => '99001',
            'employee_name' => 'Budi PIC',
            'active' => 1,
        ]);

        $category = Category::factory()->create([
            'code' => 'ELEC',
            'name' => 'Elektronik',
        ]);
        $type = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'ELEC-TV',
            'name' => 'Televisi',
            'is_consumable' => false,
        ]);
        $brand = Brand::factory()->create(['name' => 'Sony']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $organizer = Organizer::factory()->create(['name' => 'DIV-GA']);
        $this->location = Location::factory()->create();
        $vendor = Vendor::factory()->create(['name' => 'PT Mitra Sejahtera']);

        $model = InventoryModel::factory()->create([
            'number' => 'ELEC-TV-0001',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $this->lot = Lot::factory()->create([
            'number' => 'LOT-TV-001',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_single_asset_update_assigns_to_location_and_updates_status_to_di_assign_lt(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
            'location_pic_id' => $this->pic->id,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $asset->fresh();
        $this->assertTrue($fresh->is_assigned_to_location);
        $this->assertSame($this->pic->id, $fresh->location_pic_id);
        $this->assertSame('Di-assign (LT)', $fresh->status);

        // Verify lifecycle entry
        $lifecycles = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Assignment')
            ->get();
        $this->assertCount(1, $lifecycles);
        $log = $lifecycles->first();
        $this->assertSame('Assignment', $log->action_type);
        $this->assertSame('Di-assign (LT)', $log->status);
        $this->assertSame('Bagus', $log->condition);
        $this->assertSame("Di-assign ke lokasi {$this->location->name}, dengan PIC Budi PIC (99001)", $log->note);
        $this->assertSame($this->user->id, $log->actor_id);
        $this->assertNull($log->end_date);

        // Verify dedicated asset_location_assignments entry
        $assignment = AssetLocationAssignment::where('asset_id', $asset->id)->first();
        $this->assertNotNull($assignment);
        $this->assertSame($this->pic->id, $assignment->pic_user_id);
        $this->assertSame($this->location->id, $assignment->location_id);
        $this->assertNull($assignment->unassigned_at);

        // Verify no duplicate 'Perubahan status' log was created
        $statusLogs = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Perubahan status')
            ->count();
        $this->assertSame(0, $statusLogs);
    }

    public function test_single_asset_update_unassigns_from_location_and_reverts_status_to_tersedia(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'location_pic_id' => $this->pic->id,
            'active_status' => 'Aktif',
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        AssetLocationAssignment::create([
            'asset_id' => $asset->id,
            'location_id' => $this->location->id,
            'pic_user_id' => $this->pic->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => null,
        ]);

        $response = $this->actingAs($this->user)->post("/smart/stock-management/assets/{$asset->id}", [
            '_method' => 'PUT',
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'warranty_date' => null,
            'is_assigned_to_location' => false,
            'location_pic_id' => null,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $asset->fresh();
        $this->assertFalse($fresh->is_assigned_to_location);
        $this->assertNull($fresh->location_pic_id);
        $this->assertSame('Tersedia', $fresh->status);

        $assignment = AssetLocationAssignment::where('asset_id', $asset->id)->first();
        $this->assertNotNull($assignment->unassigned_at);

        // Verify assignment unassign lifecycle entry
        $log = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Assignment')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('Tersedia', $log->status);
        $this->assertSame("Assignment ke lokasi {$this->location->name} selesai.", $log->note);
    }

    public function test_single_asset_update_fails_if_status_is_not_available_when_assigning(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-03',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Dipinjam (ST)',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $response->assertSessionHasErrors(['is_assigned_to_location']);
        $this->assertFalse($asset->fresh()->is_assigned_to_location);
        $this->assertSame('Dipinjam (ST)', $asset->fresh()->status);
    }

    public function test_bulk_asset_update_assigns_all_selected_available_assets(): void
    {
        $asset1 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'is_assigned_to_location' => false,
        ]);
        $asset2 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'is_assigned_to_location' => true,
            'location_pic_id' => $this->pic->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($asset1->fresh()->is_assigned_to_location);
        $this->assertSame($this->pic->id, $asset1->fresh()->location_pic_id);
        $this->assertSame('Di-assign (LT)', $asset1->fresh()->status);
        $this->assertTrue($asset2->fresh()->is_assigned_to_location);
        $this->assertSame($this->pic->id, $asset2->fresh()->location_pic_id);
        $this->assertSame('Di-assign (LT)', $asset2->fresh()->status);

        $this->assertSame(1, AssetLifecycle::where('asset_id', $asset1->id)->where('action_type', 'Assignment')->count());
        $this->assertSame(1, AssetLifecycle::where('asset_id', $asset2->id)->where('action_type', 'Assignment')->count());
    }

    public function test_bulk_asset_update_fails_if_any_selected_asset_is_not_available(): void
    {
        $asset1 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'is_assigned_to_location' => false,
        ]);
        $asset2 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Dipinjam (ST)',
            'is_assigned_to_location' => false,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'is_assigned_to_location' => true,
        ]);

        $response->assertSessionHasErrors(['is_assigned_to_location']);
        $this->assertFalse($asset1->fresh()->is_assigned_to_location);
        $this->assertSame('Tersedia', $asset1->fresh()->status);
        $this->assertFalse($asset2->fresh()->is_assigned_to_location);
        $this->assertSame('Dipinjam (ST)', $asset2->fresh()->status);
    }

    public function test_bulk_asset_update_unassigns_assets_reverting_di_assign_lt_to_tersedia(): void
    {
        $asset1 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'is_assigned_to_location' => true,
        ]);
        $asset2 = Asset::factory()->create([
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'is_assigned_to_location' => true,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset1->id, $asset2->id],
            'is_assigned_to_location' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($asset1->fresh()->is_assigned_to_location);
        $this->assertSame('Tersedia', $asset1->fresh()->status);
        $this->assertFalse($asset2->fresh()->is_assigned_to_location);
        $this->assertSame('Tersedia', $asset2->fresh()->status);
    }

    public function test_unassign_and_location_change_simultaneously_logs_in_order(): void
    {
        $oldLocation = $this->location;
        $newLocation = Location::factory()->create(['name' => 'Lokasi Baru']);

        $asset = Asset::factory()->create([
            'number' => 'AST-ORDER-01',
            'lot_id' => $this->lot->id,
            'location_id' => $oldLocation->id,
            'active_status' => 'Aktif',
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $newLocation->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $asset->fresh();
        $this->assertFalse($fresh->is_assigned_to_location);
        $this->assertEquals($newLocation->id, $fresh->location_id);
        $this->assertSame('Tersedia', $fresh->status);

        $lifecycles = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', '!=', 'Registrasi')
            ->orderBy('id', 'asc')
            ->get();
        // Expect Assignment completion first (at old location), then Pemindahan (to new location)
        $this->assertCount(2, $lifecycles);
        $this->assertSame('Assignment', $lifecycles[0]->action_type);
        $this->assertStringContainsString('selesai', $lifecycles[0]->note);
        $this->assertEquals($oldLocation->id, $lifecycles[0]->location_id);

        $this->assertSame('Pemindahan', $lifecycles[1]->action_type);
        $this->assertEquals($newLocation->id, $lifecycles[1]->location_id);
    }

    public function test_assign_to_location_and_rusak_total_simultaneously_keeps_di_assign_and_pending_dm(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-ASSIGN-DM-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('memo.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Rusak Total',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
            'location_pic_id' => $this->pic->id,
            'memo_file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $fresh = $asset->fresh();
        $this->assertTrue($fresh->is_assigned_to_location);
        $this->assertSame('Di-assign (LT)', $fresh->status);
        $this->assertSame('Pending:DM', $fresh->approval_status);

        // Verify assignment lifecycle logged
        $lifecycles = AssetLifecycle::where('asset_id', $asset->id)->get();
        $this->assertTrue($lifecycles->contains('action_type', 'Assignment'));
    }

    public function test_assign_to_location_requires_location_pic_id_on_single_and_bulk_update(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-PIC-REQ-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => false,
        ]);

        $singleResponse = $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);
        $singleResponse->assertSessionHasErrors(['location_pic_id']);

        $bulkResponse = $this->actingAs($this->user)->post('/smart/stock-management/assets/bulk-update', [
            'ids' => [$asset->id],
            'is_assigned_to_location' => true,
        ]);
        $bulkResponse->assertSessionHasErrors(['location_pic_id']);
    }

    public function test_changing_pic_while_assigned_closes_previous_assignment_and_unrelated_edits_do_not(): void
    {
        $secondPic = User::factory()->create([
            'employee_id' => '99002',
            'employee_name' => 'Siti PIC',
            'active' => 1,
        ]);

        $asset = Asset::factory()->create([
            'number' => 'AST-PIC-CHG-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'location_pic_id' => $this->pic->id,
            'active_status' => 'Aktif',
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
        ]);

        $firstAssignment = AssetLocationAssignment::create([
            'asset_id' => $asset->id,
            'location_id' => $this->location->id,
            'pic_user_id' => $this->pic->id,
            'assigned_at' => now()->subDays(2),
            'unassigned_at' => null,
        ]);

        // Unrelated QC update must NOT close active location assignment
        $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'qc_pass' => true,
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
            'location_pic_id' => $this->pic->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull($firstAssignment->fresh()->unassigned_at);

        // Changing PIC closes old assignment and opens new one
        $this->actingAs($this->user)->put("/smart/stock-management/assets/{$asset->id}", [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'is_assigned_to_location' => true,
            'location_pic_id' => $secondPic->id,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($firstAssignment->fresh()->unassigned_at);
        $secondAssignment = AssetLocationAssignment::where('asset_id', $asset->id)
            ->where('pic_user_id', $secondPic->id)
            ->first();
        $this->assertNotNull($secondAssignment);
        $this->assertNull($secondAssignment->unassigned_at);
    }

    public function test_employee_loans_endpoint_returns_active_and_historical_pic_location_assignments(): void
    {
        $asset1 = Asset::factory()->create([
            'number' => 'AST-EMP-PIC-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'location_pic_id' => $this->pic->id,
            'active_status' => 'Aktif',
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'is_assigned_to_location' => true,
        ]);
        $asset2 = Asset::factory()->create([
            'number' => 'AST-EMP-PIC-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'is_assigned_to_location' => false,
        ]);

        AssetLocationAssignment::create([
            'asset_id' => $asset1->id,
            'location_id' => $this->location->id,
            'pic_user_id' => $this->pic->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => null,
        ]);
        AssetLocationAssignment::create([
            'asset_id' => $asset2->id,
            'location_id' => $this->location->id,
            'pic_user_id' => $this->pic->id,
            'assigned_at' => now()->subDays(5),
            'unassigned_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/smart/employee-list/{$this->pic->id}/loans");

        $response->assertOk()
            ->assertJsonCount(1, 'active_location_assignments')
            ->assertJsonCount(1, 'location_assignment_history')
            ->assertJsonPath('active_location_assignments.0.unit_number', 'AST-EMP-PIC-01')
            ->assertJsonPath('location_assignment_history.0.unit_number', 'AST-EMP-PIC-02');
    }

    public function test_scan_show_includes_location_pic_id_is_assigned_to_location_and_users(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-SCAN-PIC-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'location_pic_id' => $this->pic->id,
            'active_status' => 'Aktif',
            'status' => 'Di-assign (LT)',
            'is_assigned_to_location' => true,
        ]);

        $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', $asset->number);
        $response = $this->actingAs($this->user)->get("/smart/scan/{$cleanCode}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/MultiRoles/ScanResult')
            ->where('asset.is_assigned_to_location', true)
            ->where('asset.location_pic_id', $this->pic->id)
            ->where('asset.active_borrowing.user_name', 'Budi PIC')
            ->where('asset.active_borrowing.request_id', null)
            ->has('users')
        );

        $detailResponse = $this->actingAs($this->user)->getJson("/smart/stock-management/assets/{$asset->id}");
        $detailResponse->assertOk()
            ->assertJsonPath('active_borrowing.user_name', 'Budi PIC')
            ->assertJsonPath('active_borrowing.request_id', null);
    }

    public function test_compact_units_maps_serial_number_warranty_date_and_location_pic_id_accurately(): void
    {
        $asset = Asset::factory()->create([
            'number' => 'AST-COMPACT-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'location_pic_id' => $this->pic->id,
            'active_status' => 'Aktif',
            'status' => 'Di-assign (LT)',
            'condition' => 'Bagus',
            'is_assigned_to_location' => true,
            'serial_number' => null,
            'warranty_date' => null,
            'is_non_owned' => false,
        ]);

        $compact = app(\App\Services\Inventory\StockInventoryQueryService::class)
            ->getCompactUnitsPayload(['lot_id' => $this->lot->id]);

        $row = collect($compact['rows'])->firstWhere(0, $asset->id);
        $this->assertNotNull($row);
        $sparse = $row[8] ?? [];

        $this->assertArrayNotHasKey('sn', $sparse);
        $this->assertArrayNotHasKey('wd', $sparse);
        $this->assertArrayNotHasKey('no', $sparse);
        $this->assertSame(1, $sparse['al'] ?? null);
        $this->assertSame($this->pic->id, $sparse['lpic'] ?? null);
    }
}
