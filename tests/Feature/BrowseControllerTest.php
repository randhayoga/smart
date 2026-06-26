<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\AdmUser as User;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable the unit test admin bypass so we can test actual user behavior
        config(['app.disable_test_admin_bypass' => true]);
    }

    public function test_user_can_access_browse_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('smart.browse'));

        $response->assertStatus(200);
    }

    public function test_user_can_add_consumable_item_to_cart(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_consumable' => true,
        ]);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $response = $this->actingAs($user)->post(route('smart.asset-cart.store'), [
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('consumable_baskets', [
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 3,
        ]);
    }

    public function test_user_can_add_consumable_item_without_specific_variant(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_consumable' => true,
        ]);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($user)->post(route('smart.asset-cart.store'), [
            'subcategory_id' => $subcategory->id,
            'barang_id' => null,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('consumable_baskets', [
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => null,
            'quantity' => 5,
        ]);
    }

    public function test_user_can_add_non_consumable_item_to_cart(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_consumable' => false,
        ]);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $response = $this->actingAs($user)->post(route('smart.borrow-cart.store'), [
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('asset_baskets', [
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 1,
        ]);

        // Verify start_date and end_date are not null
        $basketItem = \App\Models\Cart\AssetBasket::where([
            'user_id' => $user->id,
            'barang_id' => $barang->id,
        ])->first();

        $this->assertNotNull($basketItem);
        $this->assertNotNull($basketItem->start_date);
        $this->assertNotNull($basketItem->end_date);
    }

    public function test_user_can_add_non_consumable_item_without_specific_variant(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_consumable' => false,
        ]);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($user)->post(route('smart.borrow-cart.store'), [
            'subcategory_id' => $subcategory->id,
            'barang_id' => null,
            'quantity' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('asset_baskets', [
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => null,
            'quantity' => 2,
        ]);

        // Verify start_date and end_date are not null
        $basketItem = \App\Models\Cart\AssetBasket::where([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => null,
        ])->first();

        $this->assertNotNull($basketItem);
        $this->assertNotNull($basketItem->start_date);
        $this->assertNotNull($basketItem->end_date);
    }

    public function test_user_can_confirm_consumable_cart_and_create_request(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = \App\Models\Cart\ConsumableBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 5,
        ]);

        $orgchart = \App\Models\HrdOrgchart::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.asset-cart.confirmation.store'), [
            'items' => [
                ['id' => $basketItem->id],
            ],
            'pemanfaatan' => 'corporate',
            'departemen' => (string) $orgchart->id,
            'alasan' => 'Testing consumable request',
        ]);

        $response->assertRedirect(route('smart.user.dashboard'));

        // Check basket is deleted
        $this->assertDatabaseMissing('consumable_baskets', [
            'id' => $basketItem->id,
        ]);

        // Check request and request item are created with subcategory_id
        $this->assertDatabaseHas('requests', [
            'user_id' => $user->id,
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'reasoning' => 'Testing consumable request',
        ]);

        $request = \App\Models\Request\Request::where('user_id', $user->id)->first();
        $this->assertNotNull($request);

        $this->assertDatabaseHas('request_items', [
            'request_id' => $request->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity_requested' => 5,
        ]);
    }

    public function test_user_can_confirm_borrow_cart_and_create_request(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = \App\Models\Cart\AssetBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 2,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ]);

        $orgchart = \App\Models\HrdOrgchart::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.borrow-cart.confirmation.store'), [
            'items' => [
                ['id' => $basketItem->id],
            ],
            'pemanfaatan' => 'corporate',
            'departemen' => (string) $orgchart->id,
            'alasan' => 'Testing borrow request',
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('smart.user.dashboard'));

        // Check basket is deleted
        $this->assertDatabaseMissing('asset_baskets', [
            'id' => $basketItem->id,
        ]);

        // Check request and request item are created with subcategory_id
        $this->assertDatabaseHas('requests', [
            'user_id' => $user->id,
            'utilization' => 'corporate',
            'org_id' => $orgchart->id,
            'reasoning' => 'Testing borrow request',
        ]);

        $request = \App\Models\Request\Request::where('user_id', $user->id)->first();
        $this->assertNotNull($request);

        $this->assertDatabaseHas('request_items', [
            'request_id' => $request->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity_requested' => 2,
        ]);
    }
}
