<?php

namespace Tests\Feature;

use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Organizer;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.categories.store'), [
            'code' => 'ATKS',
            'name' => 'Alat Tulis Kantor',
            'is_consumable' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'code' => 'ATKS',
            'name' => 'Alat Tulis Kantor',
            'is_consumable' => true,
        ]);
    }

    public function test_can_update_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS', 'name' => 'Lama']);

        $response = $this->actingAs($user)->put(route('smart.master.categories.update', $category), [
            'code' => 'ATKS',
            'name' => 'Alat Tulis Terbaru',
            'is_consumable' => '0',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Alat Tulis Terbaru',
            'is_consumable' => false,
        ]);
    }

    public function test_can_destroy_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.categories.destroy', $category));

        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_cannot_destroy_category_if_has_subcategories(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Subcategory::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Kategori tidak dapat dihapus karena masih memiliki subkategori.');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_can_store_location(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.locations.store'), [
            'name' => 'Gedung Utama',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', ['name' => 'Gedung Utama']);
    }

    public function test_can_update_location(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['name' => 'Gedung A']);

        $response = $this->actingAs($user)->put(route('smart.master.locations.update', $location), [
            'name' => 'Gedung B',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Gedung B']);
    }

    public function test_can_destroy_location(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.locations.destroy', $location));

        $response->assertRedirect();
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_cannot_destroy_location_if_has_floors(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();
        Floor::factory()->create(['location_id' => $location->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.locations.destroy', $location));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Lokasi tidak dapat dihapus karena masih memiliki lantai.');
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_can_store_organizer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.organizers.store'), [
            'name' => 'Divisi IT',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organizers', ['name' => 'Divisi IT']);
    }

    public function test_can_update_organizer(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create(['name' => 'IT']);

        $response = $this->actingAs($user)->put(route('smart.master.organizers.update', $organizer), [
            'name' => 'IT & Operations',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organizers', ['id' => $organizer->id, 'name' => 'IT & Operations']);
    }

    public function test_can_destroy_organizer(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.organizers.destroy', $organizer));

        $response->assertRedirect();
        $this->assertDatabaseMissing('organizers', ['id' => $organizer->id]);
    }

    public function test_cannot_destroy_organizer_if_used_by_lot(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        Lot::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.organizers.destroy', $organizer));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Organizer tidak dapat dihapus karena sedang digunakan oleh data lot barang.');
        $this->assertDatabaseHas('organizers', ['id' => $organizer->id]);
    }

    public function test_can_store_uom(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.uoms.store'), [
            'name' => 'Unit',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('uoms', ['name' => 'Unit']);
    }

    public function test_can_update_uom(): void
    {
        $user = User::factory()->create();
        $uom = Uom::factory()->create(['name' => 'Pcs']);

        $response = $this->actingAs($user)->put(route('smart.master.uoms.update', $uom), [
            'name' => 'Pieces',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('uoms', ['id' => $uom->id, 'name' => 'Pieces']);
    }

    public function test_can_destroy_uom(): void
    {
        $user = User::factory()->create();
        $uom = Uom::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.uoms.destroy', $uom));

        $response->assertRedirect();
        $this->assertDatabaseMissing('uoms', ['id' => $uom->id]);
    }

    public function test_cannot_destroy_uom_if_used_by_barang(): void
    {
        $user = User::factory()->create();
        $uom = Uom::factory()->create();
        Barang::factory()->create(['uom_id' => $uom->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.uoms.destroy', $uom));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Satuan tidak dapat dihapus karena sedang digunakan oleh data barang.');
        $this->assertDatabaseHas('uoms', ['id' => $uom->id]);
    }
}
