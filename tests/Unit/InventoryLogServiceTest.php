<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Services\InventoryLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    private InventoryLogService $service;
    private User $user;
    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryLogService::class);
        $this->user = User::factory()->create();

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create(['name' => 'Pcs']);

        $this->barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Spidol Hitam',
            'number' => 'BRG-001',
        ]);
    }

    public function test_log_barang_created(): void
    {
        $log = $this->service->logBarangCreated($this->barang, $this->user);

        $this->assertInstanceOf(InventoryLog::class, $log);
        $this->assertEquals($this->barang->id, $log->barang_id);
        $this->assertNull($log->lot_id);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals('create', $log->action_type);
        $this->assertEquals(0, $log->quantity_change);
        $this->assertNull($log->previous_state);
        $this->assertIsArray($log->new_state);
        $this->assertEquals('Spidol Hitam', $log->new_state['name']);
        $this->assertStringContainsString('Menambahkan tipe barang baru: Spidol Hitam (BRG-001)', $log->note);
    }

    public function test_log_barang_updated_records_diff_when_changed(): void
    {
        $original = $this->barang->getAttributes();
        $this->barang->name = 'Spidol Biru';
        $this->barang->save();

        $log = $this->service->logBarangUpdated($this->barang, $original, $this->user);

        $this->assertNotNull($log);
        $this->assertEquals('update', $log->action_type);
        $this->assertEquals(0, $log->quantity_change);
        $this->assertEquals(['name' => 'Spidol Hitam'], $log->previous_state);
        $this->assertEquals(['name' => 'Spidol Biru'], $log->new_state);
    }

    public function test_log_barang_updated_returns_null_when_no_tracked_field_changed(): void
    {
        $original = $this->barang->getAttributes();
        $log = $this->service->logBarangUpdated($this->barang, $original, $this->user);

        $this->assertNull($log);
    }

    public function test_prepare_and_log_barang_deleted_unlinks_foreign_keys_safely(): void
    {
        // First, create an existing log referencing the barang
        $existingLog = $this->service->logBarangCreated($this->barang, $this->user);
        $this->assertEquals($this->barang->id, $existingLog->barang_id);

        // Perform prepareAndLogBarangDeleted
        $deleteLog = $this->service->prepareAndLogBarangDeleted($this->barang, $this->user);

        // The existing log must now have barang_id null
        $this->assertNull($existingLog->fresh()->barang_id);

        // The delete log itself should have barang_id null, previous_state populated
        $this->assertNull($deleteLog->barang_id);
        $this->assertEquals('delete', $deleteLog->action_type);
        $this->assertEquals('Spidol Hitam', $deleteLog->previous_state['name']);
        $this->assertStringContainsString('Menghapus tipe barang: Spidol Hitam', $deleteLog->note);
    }

    public function test_log_lot_created(): void
    {
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'initial_quantity' => 50,
            'current_quantity' => 50,
            'number' => 'LOT-2026-001',
            'po_number' => 'PO-999',
        ]);

        $log = $this->service->logLotCreated($lot, $this->user);

        $this->assertInstanceOf(InventoryLog::class, $log);
        $this->assertEquals($this->barang->id, $log->barang_id);
        $this->assertEquals($lot->id, $log->lot_id);
        $this->assertEquals('stock_in', $log->action_type);
        $this->assertEquals(50, $log->quantity_change);
        $this->assertNull($log->previous_state);
        $this->assertEquals(50, $log->new_state['initial_quantity']);
        $this->assertStringContainsString('Penerimaan LOT baru LOT-2026-001 sebanyak 50 Pcs (PO: PO-999)', $log->note);
    }

    public function test_log_lot_created_with_null_po_number(): void
    {
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'initial_quantity' => 20,
            'current_quantity' => 20,
            'number' => 'LOT-2026-002',
            'po_number' => null,
        ]);

        $log = $this->service->logLotCreated($lot, $this->user);

        $this->assertInstanceOf(InventoryLog::class, $log);
        $this->assertNull($log->new_state['po_number']);
        $this->assertEquals('Penerimaan LOT baru LOT-2026-002 sebanyak 20 Pcs', $log->note);
    }

    public function test_log_lot_updated(): void
    {
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'po_number' => 'PO-OLD',
        ]);

        $original = $lot->getAttributes();
        $lot->po_number = 'PO-NEW';
        $lot->save();

        $log = $this->service->logLotUpdated($lot, $original, $this->user);

        $this->assertNotNull($log);
        $this->assertEquals('update', $log->action_type);
        $this->assertEquals(['po_number' => 'PO-OLD'], $log->previous_state);
        $this->assertEquals(['po_number' => 'PO-NEW'], $log->new_state);
    }

    public function test_prepare_and_log_lot_deleted_unlinks_foreign_keys_safely(): void
    {
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'current_quantity' => 15,
        ]);

        $existingLog = $this->service->logLotCreated($lot, $this->user);
        $this->assertEquals($lot->id, $existingLog->lot_id);

        $deleteLog = $this->service->prepareAndLogLotDeleted($lot, $this->user);

        $this->assertNull($existingLog->fresh()->lot_id);
        $this->assertNull($deleteLog->lot_id);
        $this->assertEquals($this->barang->id, $deleteLog->barang_id);
        $this->assertEquals('delete', $deleteLog->action_type);
        $this->assertEquals(-15, $deleteLog->quantity_change);
    }

    public function test_log_consumable_stock_out_note_formatting(): void
    {
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'current_quantity' => 20,
        ]);

        // Scenario 1: Corporate with note
        $log1 = $this->service->logConsumableStockOut(
            barang: $this->barang,
            lot: $lot,
            deductQty: 5,
            prevQty: 20,
            newQty: 15,
            user: $this->user,
            utilization: 'corporate',
            destinationName: 'HRD',
            reasonNote: 'Untuk onboarding'
        );

        $this->assertEquals('stock_out', $log1->action_type);
        $this->assertEquals(-5, $log1->quantity_change);
        $this->assertEquals('Permintaan untuk Corporate HRD, dengan catatan "Untuk onboarding"', $log1->note);

        // Scenario 2: Corporate with null note
        $log2 = $this->service->logConsumableStockOut(
            barang: $this->barang,
            lot: $lot,
            deductQty: 3,
            prevQty: 15,
            newQty: 12,
            user: $this->user,
            utilization: 'corporate',
            destinationName: 'Finance',
            reasonNote: null
        );

        $this->assertEquals('Permintaan untuk Corporate Finance, dengan catatan "-"', $log2->note);

        // Scenario 3: Project with note
        $log3 = $this->service->logConsumableStockOut(
            barang: $this->barang,
            lot: $lot,
            deductQty: 10,
            prevQty: 12,
            newQty: 2,
            user: $this->user,
            utilization: 'project',
            destinationName: 'MRT Phase 2',
            reasonNote: 'Kebutuhan site'
        );

        $this->assertEquals('Permintaan untuk Project MRT Phase 2, dengan catatan "Kebutuhan site"', $log3->note);
    }
}
