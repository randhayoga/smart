<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests verifying that toast/flash messages returned by controllers
 * are accurately localized according to the active session locale (en or id).
 */
class LocalizationToastMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_toast_messages_are_localized_in_indonesian(): void
    {
        $user = User::factory()->create();

        // 1. Create category in Indonesian
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->post(route('smart.master.categories.store'), [
                'code' => 'CAT1',
                'name' => 'Kategori 1',
            ]);

        $response->assertSessionHas('success', 'Kategori berhasil ditambahkan.');

        $cat = Category::where('code', 'CAT1')->first();

        // 2. Update category in Indonesian
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->put(route('smart.master.categories.update', $cat), [
                'code' => 'CAT1',
                'name' => 'Kategori 1 Diperbarui',
            ]);

        $response->assertSessionHas('success', 'Kategori berhasil diperbarui.');

        // 3. Delete category in Indonesian
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->delete(route('smart.master.categories.destroy', $cat));

        $response->assertSessionHas('success', 'Kategori berhasil dihapus.');
    }

    public function test_category_toast_messages_are_localized_in_english(): void
    {
        $user = User::factory()->create();

        // 1. Create category in English
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('smart.master.categories.store'), [
                'code' => 'CAT2',
                'name' => 'Category 2',
            ]);

        $response->assertSessionHas('success', 'Category successfully added.');

        $cat = Category::where('code', 'CAT2')->first();

        // 2. Update category in English
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->put(route('smart.master.categories.update', $cat), [
                'code' => 'CAT2',
                'name' => 'Category 2 Updated',
            ]);

        $response->assertSessionHas('success', 'Category successfully updated.');

        // 3. Delete category in English
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->delete(route('smart.master.categories.destroy', $cat));

        $response->assertSessionHas('success', 'Category successfully deleted.');
    }

    public function test_barang_toast_messages_are_localized_in_indonesian_and_english(): void
    {
        $user = User::factory()->create();
        $cat = Category::factory()->create();
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        // Store Barang with locale = id
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->post(route('smart.inventory.barangs.store'), [
                'number' => 'BRG-TEST-1',
                'name' => 'Barang ID Test',
                'subcategory_id' => $sub->id,
                'brand_id' => $brand->id,
                'uom_id' => $uom->id,
            ]);

        $response->assertSessionHas('success', 'Tipe berhasil ditambahkan.');
        $barang = Barang::where('name', 'Barang ID Test')->first();

        // Update Barang with locale = en
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->put(route('smart.inventory.barangs.update', $barang), [
                'number' => 'BRG-TEST-1',
                'name' => 'Barang EN Test',
                'subcategory_id' => $sub->id,
                'brand_id' => $brand->id,
                'uom_id' => $uom->id,
            ]);

        $response->assertSessionHas('success', 'Item type successfully updated.');

        // Delete Barang with locale = en
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->delete(route('smart.inventory.barangs.destroy', $barang));

        $response->assertSessionHas('success', 'Item type successfully deleted.');
    }

    public function test_request_cancellation_toast_messages_are_localized(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000001',
            'user_id' => $user->id,
            'approver_id' => $approver->id,
            'status' => 'wait',
            'utilization' => 'corporate',
            'reasoning' => 'Test request',
        ]);

        // Cancel with locale = en
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('smart.history.cancel', $req->uuid));

        $response->assertSessionHas('success', 'Request successfully cancelled.');

        // Cancel already cancelled request with locale = en
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->post(route('smart.history.cancel', $req->uuid));

        $response->assertSessionHas('error', 'Only requests with pending status can be cancelled.');

        // Cancel already cancelled request with locale = id
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->post(route('smart.history.cancel', $req->uuid));

        $response->assertSessionHas('error', 'Hanya permintaan yang berstatus menunggu persetujuan yang dapat dibatalkan.');
    }

    public function test_role_toast_messages_are_localized(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);

        // 1. Create role with locale = id
        $response = $this->actingAs($superadmin)
            ->withSession(['locale' => 'id'])
            ->post(route('smart.access.roles.store'), [
                'name' => 'Custom Role Id',
                'description' => 'Test role',
            ]);

        $response->assertSessionHas('success', "Peran 'Custom Role Id' berhasil dibuat.");
        $role = \App\Models\Auth\Role::where('name', 'custom_role_id')->first();

        // 2. Update role with locale = en
        $response = $this->actingAs($superadmin)
            ->withSession(['locale' => 'en'])
            ->put(route('smart.access.roles.update', $role), [
                'name' => 'Custom Role En',
                'description' => 'Test role updated',
            ]);

        $response->assertSessionHas('success', "Role 'Custom Role En' successfully updated.");
        $role->refresh();

        // 3. Delete role with locale = en
        $response = $this->actingAs($superadmin)
            ->withSession(['locale' => 'en'])
            ->delete(route('smart.access.roles.destroy', $role));

        $response->assertSessionHas('success', "Role 'Custom Role En' successfully deleted.");
    }

    public function test_cart_toast_messages_are_localized(): void
    {
        $user = User::factory()->create();
        $cat = Category::factory()->create();
        $sub = Subcategory::factory()->create(['category_id' => $cat->id, 'is_consumable' => true]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $barang = Barang::factory()->create([
            'number' => 'BRG-CART-1',
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        // 1. Add to cart with locale = id
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->post(route('smart.asset-cart.store'), [
                'subcategory_id' => $sub->id,
                'barang_id' => $barang->id,
                'quantity' => 2,
            ]);

        $response->assertSessionHas('success', 'Barang berhasil ditambahkan ke keranjang!');
        $basketItem = \App\Models\Cart\ConsumableBasket::where('user_id', $user->id)->first();

        // 2. Update cart with locale = en
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->put(route('smart.asset-cart.update', $basketItem->id), [
                'quantity' => 5,
            ]);

        $response->assertSessionHas('success', 'Item quantity updated.');

        // 3. Remove from cart with locale = en
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->delete(route('smart.asset-cart.destroy', $basketItem->id));

        $response->assertSessionHas('success', 'Item removed from cart.');
    }
}
