<?php

namespace Tests\Feature;

use App\Models\HrdOrgchart;
use App\Models\Master\Location;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Vendor;
use App\Models\User;
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

    public function test_can_store_location_with_related_department(): void
    {
        $user = User::factory()->create();
        $department = HrdOrgchart::create([
            'org_code' => 'HRD',
            'org_name' => 'Human Resources Department',
        ]);

        $response = $this->actingAs($user)->post(route('smart.master.locations.store'), [
            'name' => 'Ruang HRD',
            'related_departement' => $department->id,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', [
            'name' => 'Ruang HRD',
            'related_departement' => $department->id,
            'is_active' => 1,
        ]);
    }

    public function test_cannot_store_location_with_invalid_related_department(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.locations.store'), [
            'name' => 'Ruang Invalid Dept',
            'related_departement' => 999999,
        ]);

        $response->assertSessionHasErrors(['related_departement']);
    }

    public function test_can_update_location_related_department(): void
    {
        $user = User::factory()->create();
        $department1 = HrdOrgchart::create([
            'org_code' => 'FIN',
            'org_name' => 'Finance Department',
        ]);
        $department2 = HrdOrgchart::create([
            'org_code' => 'ACC',
            'org_name' => 'Accounting Department',
        ]);

        $location = Location::factory()->create([
            'name' => 'Ruang Keuangan',
            'related_departement' => $department1->id,
        ]);

        // Update to department 2
        $response = $this->actingAs($user)->put(route('smart.master.locations.update', $location), [
            'name' => 'Ruang Akuntansi',
            'related_departement' => $department2->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Ruang Akuntansi',
            'related_departement' => $department2->id,
        ]);

        // Clear department to null
        $response = $this->actingAs($user)->put(route('smart.master.locations.update', $location), [
            'name' => 'Ruang Akuntansi',
            'related_departement' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'related_departement' => null,
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

        // Test with 4-character suffix
        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-AAAA',
            'name' => 'Subcategory Baru',
            'description' => 'Deskripsi testing subcategory',
            'is_consumable' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', [
            'category_id' => $category->id,
            'code' => 'ATKS-AAAA',
            'name' => 'Subcategory Baru',
            'description' => 'Deskripsi testing subcategory',
            'is_consumable' => true,
        ]);

        // Test with 2-character suffix
        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-BB',
            'name' => 'Subcategory Dua Huruf',
            'is_consumable' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', [
            'category_id' => $category->id,
            'code' => 'ATKS-BB',
            'name' => 'Subcategory Dua Huruf',
        ]);

        // Test with 3-character suffix
        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-CCC',
            'name' => 'Subcategory Tiga Huruf',
            'is_consumable' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', [
            'category_id' => $category->id,
            'code' => 'ATKS-CCC',
            'name' => 'Subcategory Tiga Huruf',
        ]);
    }

    public function test_cannot_store_subcategory_with_invalid_category_prefix(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS']);

        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
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

        // 1-char suffix (below min length 2)
        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-A',
            'name' => 'Subcategory Satu Huruf',
        ]);
        $response->assertSessionHasErrors(['code']);

        // 5-char suffix (invalid length > 4)
        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-AAAAA',
            'name' => 'Subcategory Lima Huruf',
        ]);
        $response->assertSessionHasErrors(['code']);
    }

    public function test_can_store_subcategory_with_digits_in_suffix(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['code' => 'ATKS']);

        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-1234', // digits allowed
            'name' => 'Subcategory Angka',
            'is_consumable' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', [
            'category_id' => $category->id,
            'code' => 'ATKS-1234',
            'name' => 'Subcategory Angka',
        ]);

        // alphanumeric combo
        $response = $this->actingAs($user)->post(route('smart.master.types.store'), [
            'category_id' => $category->id,
            'code' => 'ATKS-A1',
            'name' => 'Subcategory Alfanumerik',
            'is_consumable' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', [
            'category_id' => $category->id,
            'code' => 'ATKS-A1',
            'name' => 'Subcategory Alfanumerik',
        ]);
    }

    public function test_can_update_subcategory_with_description(): void
    {
        $user = User::factory()->create();
        $subcategory = Subcategory::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.master.types.update', $subcategory), [
            'name' => 'Subcategory Updated',
            'description' => 'Updated Description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', [
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
        $response->assertSessionHas('error', __('master.brands.cannot_delete_used'));
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
        ]);
    }

    public function test_can_store_vendor_with_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN00009',
            'name' => 'Vendor Baru PT',
            'address' => 'Jalan Baru No. 12',
            'phone_number' => '021-123456',
            'email' => 'vendor@baru.com',
            'description' => 'Description Vendor Baru',
            'contact_persons' => [
                [
                    'contact_person' => 'John Doe',
                    'cp_email' => 'john@baru.com',
                    'cp_phone' => '0812345678',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'code' => 'VN00009',
            'name' => 'Vendor Baru PT',
            'address' => 'Jalan Baru No. 12',
            'phone_number' => '021-123456',
            'email' => 'vendor@baru.com',
            'description' => 'Description Vendor Baru',
        ]);
        $vendor = Vendor::where('code', 'VN00009')->firstOrFail();
        $this->assertDatabaseHas('vendors_cp', [
            'vendor_id' => $vendor->id,
            'contact_person' => 'John Doe',
            'cp_email' => 'john@baru.com',
            'cp_phone' => '0812345678',
        ]);
    }

    public function test_can_update_vendor_with_all_fields(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $vendor->contactPersons()->create([
            'contact_person' => 'Old CP',
            'cp_email' => 'old@vendor.com',
            'cp_phone' => '0811111111',
        ]);

        $response = $this->actingAs($user)->put(route('smart.master.vendors.update', $vendor), [
            'code' => 'VN09999',
            'name' => 'Vendor Updated PT',
            'address' => 'Jalan Updated No. 12',
            'phone_number' => '021-654321',
            'email' => 'vendor@updated.com',
            'description' => 'Description Vendor Updated',
            'contact_persons' => [
                [
                    'contact_person' => 'Jane Doe',
                    'cp_email' => 'jane@updated.com',
                    'cp_phone' => '0898765432',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'code' => 'VN09999',
            'name' => 'Vendor Updated PT',
            'address' => 'Jalan Updated No. 12',
            'phone_number' => '021-654321',
            'email' => 'vendor@updated.com',
            'description' => 'Description Vendor Updated',
        ]);
        $this->assertDatabaseMissing('vendors_cp', [
            'vendor_id' => $vendor->id,
            'contact_person' => 'Old CP',
        ]);
        $this->assertDatabaseHas('vendors_cp', [
            'vendor_id' => $vendor->id,
            'contact_person' => 'Jane Doe',
            'cp_email' => 'jane@updated.com',
            'cp_phone' => '0898765432',
        ]);
    }

    public function test_can_store_and_update_vendor_with_multiple_contact_persons(): void
    {
        $user = User::factory()->create();

        // 1. Store vendor with 3 contact persons
        $response = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN00015',
            'name' => 'Vendor Multi CP PT',
            'contact_persons' => [
                ['contact_person' => 'CP Satu', 'cp_email' => 'satu@multi.com', 'cp_phone' => '0811'],
                ['contact_person' => 'CP Dua', 'cp_email' => 'dua@multi.com', 'cp_phone' => '0822'],
                ['contact_person' => 'CP Tiga', 'cp_email' => 'tiga@multi.com', 'cp_phone' => '0833'],
            ],
        ]);

        $response->assertRedirect();
        $vendor = Vendor::where('code', 'VN00015')->firstOrFail();
        $this->assertCount(3, $vendor->contactPersons);

        // 2. Update to 4 contact persons
        $updateResponse = $this->actingAs($user)->put(route('smart.master.vendors.update', $vendor), [
            'code' => 'VN00015',
            'name' => 'Vendor Multi CP PT Updated',
            'contact_persons' => [
                ['contact_person' => 'CP Alpha', 'cp_email' => 'alpha@multi.com', 'cp_phone' => '0811'],
                ['contact_person' => 'CP Beta', 'cp_email' => 'beta@multi.com', 'cp_phone' => '0822'],
                ['contact_person' => 'CP Gamma', 'cp_email' => 'gamma@multi.com', 'cp_phone' => '0833'],
                ['contact_person' => 'CP Delta', 'cp_email' => 'delta@multi.com', 'cp_phone' => '0844'],
            ],
        ]);

        $updateResponse->assertRedirect();
        $this->assertCount(4, $vendor->fresh()->contactPersons);
        $this->assertDatabaseHas('vendors_cp', ['vendor_id' => $vendor->id, 'contact_person' => 'CP Delta']);
    }

    public function test_can_store_vendor_without_address_and_phone_number(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN00010',
            'name' => 'Vendor Minimal PT',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'code' => 'VN00010',
            'name' => 'Vendor Minimal PT',
            'address' => null,
            'phone_number' => null,
        ]);
    }

    public function test_can_update_vendor_without_address_and_phone_number(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create([
            'address' => 'Old Address',
            'phone_number' => '0812345678',
        ]);

        $response = $this->actingAs($user)->put(route('smart.master.vendors.update', $vendor), [
            'code' => $vendor->code,
            'name' => 'Vendor Minimal Updated PT',
            'address' => null,
            'phone_number' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Vendor Minimal Updated PT',
            'address' => null,
            'phone_number' => null,
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
        \App\Models\Inventory\Lot::factory()->create(['legacy_vendor_id' => $vendor->id, 'vendor_id' => null]);

        $response = $this->actingAs($user)->delete(route('smart.master.vendors.destroy', $vendor));

        $response->assertRedirect();
        $response->assertSessionHas('error', __('master.vendors.cannot_delete_used'));
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
        ]);
    }

    public function test_dummy_consumable_seeder_increments_last_actual_vendor_code(): void
    {
        Vendor::factory()->create(['code' => 'VN00117', 'name' => 'Existing Vendor PT']);

        $seeder = new \Database\Seeders\DummyConsumableMasterSeeder();
        $seeder->run();

        $this->assertDatabaseHas('vendors', [
            'name' => 'PT Surya Abadi Mandiri',
            'code' => 'VN00118',
        ]);
        $this->assertDatabaseHas('vendors', [
            'name' => 'PT Mitra Global Solusindo',
            'code' => 'VN00119',
        ]);
    }

    public function test_vendor_phone_number_and_cp_phone_validation(): void
    {
        $user = User::factory()->create();

        // 1. Success with allowed characters: digits, +, -, and spaces
        $validResponse = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN00021',
            'name' => 'Valid Phone Vendor PT',
            'phone_number' => '+62 21-555 1234',
            'contact_persons' => [
                [
                    'contact_person' => 'Budi',
                    'cp_phone' => '+62 812-3456 7890',
                ],
            ],
        ]);
        $validResponse->assertRedirect();
        $this->assertDatabaseHas('vendors', [
            'code' => 'VN00021',
            'phone_number' => '+62 21-555 1234',
        ]);
        $this->assertDatabaseHas('vendors_cp', [
            'contact_person' => 'Budi',
            'cp_phone' => '+62 812-3456 7890',
        ]);

        // 2. Failure when phone_number contains forbidden characters (e.g. parentheses or letters)
        $invalidPhoneResponse = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN00022',
            'name' => 'Invalid Phone Vendor PT',
            'phone_number' => '(021) 555-1234',
        ]);
        $invalidPhoneResponse->assertSessionHasErrors(['phone_number']);

        // 3. Failure when contact_person cp_phone contains letters
        $invalidCpPhoneResponse = $this->actingAs($user)->post(route('smart.master.vendors.store'), [
            'code' => 'VN00023',
            'name' => 'Invalid CP Phone Vendor PT',
            'contact_persons' => [
                [
                    'contact_person' => 'Charlie',
                    'cp_phone' => '0812-CALL-ME',
                ],
            ],
        ]);
        $invalidCpPhoneResponse->assertSessionHasErrors(['contact_persons.0.cp_phone']);
    }
}
