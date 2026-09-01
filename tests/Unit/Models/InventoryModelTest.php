<?php

namespace Tests\Unit\Models;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitLifecycle;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventory Model Unit Tests
 *
 * Verifies Eloquent relationships, lifecycle logging triggers, status approval links,
 * and custom route key resolution for Barang and Lot models.
 */
class InventoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_barang_relationships(): void
    {
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        $this->assertTrue($barang->subcategory->is($subcategory));
        $this->assertTrue($barang->brand->is($brand));
        $this->assertTrue($barang->uom->is($uom));
        $this->assertTrue($barang->lots->contains($lot));
    }

    public function test_lot_relationships(): void
    {
        $barang = Barang::factory()->create();
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();
        $floor = Floor::factory()->create(['location_id' => $location->id]);
        $room = Room::factory()->create(['floor_id' => $floor->id]);

        $lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'floor_id' => $floor->id,
            'room_id' => $room->id,
        ]);

        $unit = Unit::create([
            'number' => 'AST-TEST-01',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => 100000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $this->assertTrue($lot->barang->is($barang));
        $this->assertTrue($lot->organizer->is($organizer));
        $this->assertTrue($lot->vendor->is($vendor));
        $this->assertTrue($lot->location->is($location));
        $this->assertTrue($lot->floor->is($floor));
        $this->assertTrue($lot->room->is($room));
        $this->assertTrue($lot->units->contains($unit));
    }

    public function test_unit_relationships_and_lifecycle_triggers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $lot = Lot::factory()->create();
        $location = Location::factory()->create();

        $unit = Unit::create([
            'number' => 'AST-TEST-02',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => 200000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $this->assertTrue($unit->lot->is($lot));
        $this->assertTrue($unit->location->is($location));
        $this->assertCount(1, $unit->lifecycles);
        $this->assertEquals('Registrasi', $unit->lifecycles->first()->action_type);

        // Update status to trigger lifecycle logging
        $unit->update(['status' => 'Standby']);
        $unit->refresh();
        $this->assertCount(2, $unit->lifecycles);
    }

    public function test_unit_status_approval_relationships(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $lot = Lot::factory()->create();
        $location = Location::factory()->create();

        $unit = Unit::create([
            'number' => 'AST-TEST-03',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Pending:BoD/BoC',
            'condition' => 'Rusak Total',
            'price' => 300000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $user->id,
            'approver_id' => $approver->id,
            'proposed_condition' => 'Rusak Total',
            'previous_condition' => 'Bagus',
            'previous_status' => 'Tersedia',
            'decision' => 'approved',
            'note' => 'Disetujui',
            'memo_url' => 'memos/placeholder.pdf',
            'requested_at' => now(),
        ]);

        $this->assertTrue($approval->unit->is($unit));
        $this->assertTrue($approval->requester->is($user));
        $this->assertTrue($approval->approver->is($approver));
        $this->assertTrue($unit->statusApprovals->contains($approval));
    }

    public function test_barang_route_key_and_resolution(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'TIP-ROUTE-001',
        ]);

        $this->assertEquals('number', $barang->getRouteKeyName());

        // Resolves by code
        $resolvedByCode = (new Barang())->resolveRouteBinding('TIP-ROUTE-001');
        $this->assertNotNull($resolvedByCode);
        $this->assertTrue($resolvedByCode->is($barang));

        // Resolves by numeric ID (backward compatibility)
        $resolvedById = (new Barang())->resolveRouteBinding((string) $barang->id);
        $this->assertNotNull($resolvedById);
        $this->assertTrue($resolvedById->is($barang));
    }

    public function test_lot_route_key_and_resolution(): void
    {
        $lot = Lot::factory()->create([
            'number' => 'LOT-ROUTE-001',
        ]);

        $this->assertEquals('number', $lot->getRouteKeyName());

        // Resolves by code
        $resolvedByCode = (new Lot())->resolveRouteBinding('LOT-ROUTE-001');
        $this->assertNotNull($resolvedByCode);
        $this->assertTrue($resolvedByCode->is($lot));

        // Resolves by numeric ID (backward compatibility)
        $resolvedById = (new Lot())->resolveRouteBinding((string) $lot->id);
        $this->assertNotNull($resolvedById);
        $this->assertTrue($resolvedById->is($lot));
    }
}
