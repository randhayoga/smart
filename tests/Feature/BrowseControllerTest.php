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

        $response = $this->actingAs($user)->post(route('smart.browse.add-to-cart'), [
            'barang_id' => $barang->id,
            'quantity' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('consumable_baskets', [
            'user_id' => $user->id,
            'barang_id' => $barang->id,
            'quantity' => 3,
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

        $response = $this->actingAs($user)->post(route('smart.browse.add-to-cart'), [
            'barang_id' => $barang->id,
            'quantity' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('asset_baskets', [
            'user_id' => $user->id,
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
}
