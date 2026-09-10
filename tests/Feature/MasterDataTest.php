<?php

namespace Tests\Feature;

use App\Models\Master\Location;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Vendor;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Data Extended Feature Tests
 *
 * Verifies validation patterns, hierarchical integrity, and CRUD handlers for Locations, Subcategories, Brands, and Vendors.
 */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_root_location(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.locations.store'), [
            'name' => 'Graha RE 1',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', [
            'name' => 'Graha RE 1',
            'parent_id' => null,
            'is_active' => 1,
        ]);
    }

    public function test_can_store_nested_location(): void
    {
        $user = User::factory()->create();
        $parent = Location::factory()->create(['name' => 'Graha RE 1']);

        $response = $this->actingAs($user)->post(route('smart.master.locations.store'), [
            'name' => 'Lantai Mezzanine',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', [
            'name' => 'Lantai Mezzanine',
            'parent_id' => $parent->id,
            'is_active' => 1,
        ]);
    }

    public function test_can_update_location_parent_and_name(): void
    {
        $user = User::factory()->create();
        $parent1 = Location::factory()->create(['name' => 'Graha RE 1']);
        $parent2 = Location::factory()->create(['name' => 'Graha RE 2']);
        $child = Location::factory()->create(['name' => 'Lantai 1', 'parent_id' => $parent1->id]);

        $response = $this->actingAs($user)->put(route('smart.master.locations.update', $child), [
            'name' => 'Lantai Satu',
            'parent_id' => $parent2->id,
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', [
            'id' => $child->id,
            'name' => 'Lantai Satu',
            'parent_id' => $parent2->id,
            'is_active' => 0,
        ]);
    }

    public function test_cannot_set_location_parent_to_self(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['name' => 'Building A']);

        $response = $this->actingAs($user)->put(route('smart.master.locations.update', $location), [
            'name' => 'Building A Updated',
            'parent_id' => $location->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_set_location_parent_to_descendant(): void
    {
        $user = User::factory()->create();
        $parent = Location::factory()->create(['name' => 'Root']);
        $child = Location::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);
        $grandchild = Location::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $response = $this->actingAs($user)->put(route('smart.master.locations.update', $parent), [
            'name' => 'Root Updated',
            'parent_id' => $grandchild->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_destroy_location_with_children(): void
    {
        $user = User::factory()->create();
        $parent = Location::factory()->create(['name' => 'Parent']);
        Location::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.locations.destroy', $parent));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Lokasi tidak dapat dihapus karena masih memiliki sub-lokasi.');
        $this->assertDatabaseHas('locations', ['id' => $parent->id]);
    }

    public function test_can_destroy_leaf_location(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['name' => 'Leaf']);

        $response = $this->actingAs($user)->delete(route('smart.master.locations.destroy', $location));

        $response->assertRedirect();
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_toggle_location_active(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->patch(route('smart.master.locations.toggle-active', $location));
        $response->assertRedirect();
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'is_active' => 0]);

        $response = $this->actingAs($user)->patch(route('smart.master.locations.toggle-active', $location));
        $response->assertRedirect();
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'is_active' => 1]);
    }

    public function test_can_store_subcategory_with_valid_format(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS']);

        $response = $this->actingAs($user)->post(route('smart.master.subcategories.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-AAAA',
            'name' => 'Subcategory Baru',
            'description' => 'Deskripsi testing subcategory',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subcategories', [
            'category_id' => $category->id,
            'code' => 'ATKS-AAAA',
            'name' => 'Subcategory Baru',
            'description' => 'Deskripsi testing subcategory',
        ]);
    }

    public function test_cannot_store_subcategory_with_invalid_category_prefix(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS']);

        $response = $this->actingAs($user)->post(route('smart.master.subcategories.store'), [
            'category_id' => $category->id,
            'code' => 'FURN-AAAA', // invalid prefix
            'name' => 'Subcategory Baru',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_cannot_store_subcategory_with_invalid_suffix_length(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS']);

        $response = $this->actingAs($user)->post(route('smart.master.subcategories.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-AAAAA', // 5-char suffix (invalid length)
            'name' => 'Subcategory Baru',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_cannot_store_subcategory_with_digits_in_suffix(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS']);

        $response = $this->actingAs($user)->post(route('smart.master.subcategories.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-1234', // digits instead of alphabets
            'name' => 'Subcategory Baru',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_can_update_subcategory_with_description(): void
    {
        $user = User::factory()->create();
        $subcategory = Subcategory::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.master.subcategories.update', $subcategory), [
            'name' => 'Subcategory Updated',
            'description' => 'Updated Description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subcategories', [
            'id' => $subcategory->id,
            'name' => 'Subcategory Updated',
            'description' => 'Updated Description',
        ]);
    }

    public function test_can_store_brand_with_description(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.brands.store'), [
            'name' => 'Brand Baru',
            'description' => 'Deskripsi testing brand',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('brands', [
            'name' => 'Brand Baru',
            'description' => 'Deskripsi testing brand',
        ]);
    }

    public function test_can_update_brand_with_description(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.master.brands.update', $brand), [
            'name' => 'Brand Updated',
            'description' => 'Updated Description Brand',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Brand Updated',
            'description' => 'Updated Description Brand',
        ]);
    }

    public function test_can_destroy_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.brands.destroy', $brand));

        $response->assertRedirect();
        $this->assertDatabaseMissing('brands', [
            'id' => $brand->id,
        ]);
    }

    public function test_cannot_destroy_brand_if_used_by_barang(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        \App\Models\Inventory\Barang::factory()->create(['brand_id' => $brand->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.brands.destroy', $brand));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Merek tidak dapat dihapus karena sedang digunakan oleh data barang.');
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
        ]);
    }

    public function test_can_store_vendor_with_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN0009',
            'name' => 'Vendor Baru PT',
            'address' => 'Jalan Baru No. 12',
            'phone_number' => '021-123456',
            'email' => 'vendor@baru.com',
            'description' => 'Description Vendor Baru',
            'contact_person_1' => 'John Doe',
            'cp_email_1' => 'john@baru.com',
            'cp_phone_1' => '0812345678',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'code' => 'VN0009',
            'name' => 'Vendor Baru PT',
            'address' => 'Jalan Baru No. 12',
            'phone_number' => '021-123456',
            'email' => 'vendor@baru.com',
            'description' => 'Description Vendor Baru',
            'contact_person_1' => 'John Doe',
            'cp_email_1' => 'john@baru.com',
            'cp_phone_1' => '0812345678',
        ]);
    }

    public function test_can_update_vendor_with_all_fields(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.master.vendors.update', $vendor), [
            'code' => 'VN9999',
            'name' => 'Vendor Updated PT',
            'address' => 'Jalan Updated No. 12',
            'phone_number' => '021-654321',
            'email' => 'vendor@updated.com',
            'description' => 'Description Vendor Updated',
            'contact_person_1' => 'Jane Doe',
            'cp_email_1' => 'jane@updated.com',
            'cp_phone_1' => '0898765432',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'code' => 'VN9999',
            'name' => 'Vendor Updated PT',
            'address' => 'Jalan Updated No. 12',
            'phone_number' => '021-654321',
            'email' => 'vendor@updated.com',
            'description' => 'Description Vendor Updated',
            'contact_person_1' => 'Jane Doe',
            'cp_email_1' => 'jane@updated.com',
            'cp_phone_1' => '0898765432',
        ]);
    }

    public function test_can_destroy_vendor(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.vendors.destroy', $vendor));

        $response->assertRedirect();
        $this->assertDatabaseMissing('vendors', [
            'id' => $vendor->id,
        ]);
    }

    public function test_cannot_destroy_vendor_if_used_by_lot(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();
        \App\Models\Inventory\Lot::factory()->create(['vendor_id' => $vendor->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.vendors.destroy', $vendor));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Vendor tidak dapat dihapus karena sedang digunakan oleh data lot barang.');
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
        ]);
    }
}
