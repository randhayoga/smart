<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lot Controller Feature Tests
 *
 * Verifies single and bulk operations on LOT batches, unit quantity limits, and deletion constraints.
 */
class LotControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_lot(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $category = \App\Models\Master\Category::factory()->create();
        $subcategory = \App\Models\Master\Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => false]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-2026-ATK-KER-0001-0001',
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-01',
            'purchase_grant_date' => '2026-05-22',
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('lots', [
            'number' => 'LOT-2026-ATK-KER-0001-0001',
            'model_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-01',
        ]);

        $lot = Lot::first();

        $this->assertDatabaseHas('inventory_logs', [
            'lot_id' => $lot->id,
            'model_id' => $barang->id,
            'user_id' => $user->id,
            'action_type' => 'stock_in',
        ]);

        $bulkResponse = $this->actingAs($user)->post(route('smart.inventory.assets.bulk-store'), [
            'number' => 'LOT-2026-ATK-KER-0001-0001-U01',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 60000,
            'bulk_quantity' => 3,
        ]);
        $bulkResponse->assertRedirect();

        $this->assertEquals(3, $lot->units()->count());
        $unit = $lot->units()->first();
        $this->assertMatchesRegularExpression('/^\d{5}-.+-PTRE-\d{2}$/', $unit->number);
        $this->assertEquals('Belum Diverifikasi', $unit->status);
        $this->assertEquals(60000, $unit->price);
    }

    public function test_can_update_lot(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $newOrganizer = Organizer::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.inventory.lots.update', $lot), [
            'number' => $lot->number,
            'barang_id' => $lot->barang_id,
            'organizer_id' => $newOrganizer->id,
            'vendor_id' => $lot->vendor_id,
            'po_number' => 'PO-UPDATED',
            'purchase_grant_date' => $lot->purchase_grant_date->format('Y-m-d'),
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('lots', [
            'id' => $lot->id,
            'organizer_id' => $newOrganizer->id,
            'po_number' => 'PO-UPDATED',
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'lot_id' => $lot->id,
            'user_id' => $user->id,
            'action_type' => 'update',
        ]);
    }

    public function test_can_store_lot_with_null_or_blank_po_number(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $barang = Barang::factory()->create();
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-2026-ATK-KER-0002-0001',
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'po_number' => '', // blank input
            'purchase_grant_date' => '2026-05-22',
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('lots', [
            'number' => 'LOT-2026-ATK-KER-0002-0001',
            'model_id' => $barang->id,
            'po_number' => null,
        ]);
    }

    public function test_can_update_lot_with_null_or_blank_po_number(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $lot = Lot::factory()->create(['po_number' => 'PO-INITIAL']);

        $response = $this->actingAs($user)->put(route('smart.inventory.lots.update', $lot), [
            'number' => $lot->number,
            'barang_id' => $lot->barang_id,
            'organizer_id' => $lot->organizer_id,
            'vendor_id' => $lot->vendor_id,
            'po_number' => '', // cleared to blank
            'purchase_grant_date' => $lot->purchase_grant_date->format('Y-m-d'),
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();
        $this->assertNull($lot->fresh()->po_number);
    }

    public function test_can_destroy_lot(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.destroy', $lot));

        $response->assertRedirect();
        $this->assertDatabaseMissing('lots', ['id' => $lot->id]);

        $this->assertDatabaseHas('inventory_logs', [
            'model_id' => $lot->model_id,
            'user_id' => $user->id,
            'action_type' => 'delete',
        ]);
    }

    public function test_destroy_lot_from_detail_page_redirects_to_parent_barang(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $barang = $lot->barang;

        $response = $this->actingAs($user)
            ->from(route('smart.inventory.lots.show', $lot))
            ->delete(route('smart.inventory.lots.destroy', $lot));

        $response->assertRedirect(route('smart.inventory.show', $barang));
        $this->assertDatabaseMissing('lots', ['id' => $lot->id]);
    }

    public function test_destroy_lot_with_redirect_to_parameter_redirects_to_specified_url(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $targetUrl = '/smart/inventory/custom-target';

        $response = $this->actingAs($user)
            ->delete(route('smart.inventory.lots.destroy', ['lot' => $lot, 'redirect_to' => $targetUrl]));

        $response->assertRedirect($targetUrl);
        $this->assertDatabaseMissing('lots', ['id' => $lot->id]);
    }

    public function test_cannot_destroy_lot_with_units(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $location = Location::factory()->create();
        
        $tipeCode = $lot->barang->subcategory->code ?? 'SUB';
        $organizerCode = $lot->organizer->name ?? 'ORG';
        $combination = "{$tipeCode}-{$organizerCode}-PTRE";
        $yy = $lot->purchase_grant_date ? $lot->purchase_grant_date->format('y') : date('y');
        $unitNumber = "00001-{$combination}-{$yy}";
        if (strlen($unitNumber) > 25) {
            $unitNumber = substr($unitNumber, 0, 25);
        }

        \App\Models\Inventory\Unit::create([
            'number' => $unitNumber,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 0,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.destroy', $lot));

        $response->assertRedirect();
        $response->assertSessionHas('error', __('inventory.lot_cannot_delete_has_assets'));
        $this->assertDatabaseHas('lots', [
            'id' => $lot->id,
        ]);
    }

    public function test_can_bulk_update_lots(): void
    {
        $user = User::factory()->create();
        
        $barang = Barang::factory()->create();
        $lots = Lot::factory()->count(3)->create(['barang_id' => $barang->id]);
        $ids = $lots->pluck('id')->toArray();
        
        $newOrganizer = Organizer::factory()->create();
        $newVendor = Vendor::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.inventory.lots.bulk-update'), [
            'ids' => $ids,
            'organizer_id' => $newOrganizer->id,
            'vendor_id' => $newVendor->id,
        ]);

        $response->assertRedirect();

        foreach ($ids as $id) {
            $this->assertDatabaseHas('lots', [
                'id' => $id,
                'organizer_id' => $newOrganizer->id,
                'vendor_id' => $newVendor->id,
            ]);

            $this->assertDatabaseHas('inventory_logs', [
                'lot_id' => $id,
                'user_id' => $user->id,
                'action_type' => 'update',
            ]);
        }
    }

    public function test_can_bulk_delete_lots(): void
    {
        $user = User::factory()->create();
        $lots = Lot::factory()->count(3)->create();
        $ids = $lots->pluck('id')->toArray();

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.bulk-destroy'), [
            'ids' => $ids,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '3 LOT terpilih berhasil dihapus.');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('lots', [
                'id' => $id,
            ]);
        }

        $this->assertEquals(3, InventoryLog::where('user_id', $user->id)->where('action_type', 'delete')->count());
    }

    public function test_cannot_bulk_destroy_lots_if_any_has_units(): void
    {
        $user = User::factory()->create();
        $lotWithoutUnits = Lot::factory()->create();
        $lotWithUnits = Lot::factory()->create();
        $location = Location::factory()->create();

        \App\Models\Inventory\Unit::create([
            'number' => substr($lotWithUnits->number, 0, 20) . '-U01',
            'lot_id' => $lotWithUnits->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 0,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.bulk-destroy'), [
            'ids' => [$lotWithoutUnits->id, $lotWithUnits->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', "1 LOT terpilih berhasil dihapus.\n1 LOT tidak dapat dihapus karena masih memiliki unit terkait.");

        $this->assertDatabaseMissing('lots', [
            'id' => $lotWithoutUnits->id,
        ]);
        $this->assertDatabaseHas('lots', [
            'id' => $lotWithUnits->id,
        ]);
    }

    public function test_can_bulk_update_units(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $location = Location::factory()->create();
        
        $units = [];
        for ($i = 0; $i < 3; $i++) {
            $units[] = \App\Models\Inventory\Unit::create([
                'number' => substr($lot->number, 0, 20) . '-U0' . ($i + 1),
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'type' => 'LT',
                'classification' => 'Aset',
                'price' => 0,
                'image_url' => 'inventory/lots/placeholder.jpg',
            ]);
        }
        $ids = collect($units)->pluck('id')->toArray();

        $response = $this->actingAs($user)->post(route('smart.inventory.assets.bulk-update'), [
            'ids' => $ids,
            'status' => 'Standby',
            'condition' => 'Rusak',
            'location_id' => (string) $location->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        foreach ($ids as $id) {
            $this->assertDatabaseHas('assets', [
                'id' => $id,
                'status' => 'Standby',
                'condition' => 'Rusak',
                'location_id' => $location->id,
            ]);
        }
    }

    public function test_non_consumable_lot_sets_null_quantities(): void
    {
        $user = User::factory()->create();
        $category = \App\Models\Master\Category::factory()->create();
        $subcategory = \App\Models\Master\Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => false]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();

        // Storing non-consumable without quantity sets null quantities
        $response = $this->actingAs($user)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-NONC-0001',
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'purchase_grant_date' => '2026-10-06',
        ]);

        $response->assertSessionHasNoErrors();
        $lot = Lot::where('number', 'LOT-NONC-0001')->first();
        $this->assertNotNull($lot);
        $this->assertNull($lot->initial_quantity);
        $this->assertNull($lot->current_quantity);
    }
}
