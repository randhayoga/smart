<?php

namespace Tests\Feature;

use App\Models\AdmUser as User;
use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\TbRbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Cart and Confirmation Security & Business Logic Verification Tests
 */
class UserCartSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_user_cannot_submit_confirmation_for_unassigned_project(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $pmRbs = TbRbs::create(['id' => 'P2211', 'name' => 'Project Manager', 'showing_name' => 'PM']);
        $memberRbs = TbRbs::create(['id' => 'P0', 'name' => 'Anggota', 'showing_name' => 'Anggota']);

        $project = TbProject::factory()->create(['no_project' => 'PRJ-UNASSIGNED']);

        // Assign PM to project, but do NOT assign $user
        TbAssignProject::create([
            'npk' => $otherUser->employee_id,
            'no_project' => $project->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = ConsumableBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('smart.asset-cart.confirmation.store'), [
            'items' => [['id' => $basketItem->id]],
            'pemanfaatan' => 'project',
            'project' => (string) $project->id,
            'alasan' => 'Attempting unauthorized project submission',
        ]);

        $response->assertSessionHasErrors(['project']);
        $this->assertDatabaseHas('consumable_baskets', ['id' => $basketItem->id]);
    }

    public function test_confirmation_fails_when_department_has_no_manager_and_no_fallback_to_arbitrary_user(): void
    {
        $user = User::factory()->create();

        // Department without manager (employee_id is null)
        $orgchart = HrdOrgchart::factory()->create([
            'employee_id' => null,
        ]);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = ConsumableBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->post(route('smart.asset-cart.confirmation.store'), [
            'items' => [['id' => $basketItem->id]],
            'pemanfaatan' => 'corporate',
            'departemen' => (string) $orgchart->id,
            'alasan' => 'Testing missing manager rejection',
        ]);

        $response->assertSessionHasErrors(['departemen']);
        $this->assertDatabaseHas('consumable_baskets', ['id' => $basketItem->id]);
    }

    public function test_borrow_cart_rejects_past_start_date_and_inverted_end_date(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create();

        $orgchart = HrdOrgchart::factory()->create(['employee_id' => $manager->employee_id]);

        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = AssetBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 1,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
        ]);

        // Past start date should fail
        $responsePast = $this->actingAs($user)->post(route('smart.borrow-cart.confirmation.store'), [
            'items' => [['id' => $basketItem->id]],
            'pemanfaatan' => 'corporate',
            'departemen' => (string) $orgchart->id,
            'alasan' => 'Testing past date rejection',
            'start_date' => '2020-01-01 08:00:00',
            'end_date' => '2020-01-02 17:00:00',
        ]);

        $responsePast->assertSessionHasErrors(['start_date']);

        // End date before start date should fail
        $responseInverted = $this->actingAs($user)->post(route('smart.borrow-cart.confirmation.store'), [
            'items' => [['id' => $basketItem->id]],
            'pemanfaatan' => 'corporate',
            'departemen' => (string) $orgchart->id,
            'alasan' => 'Testing inverted date rejection',
            'start_date' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $responseInverted->assertSessionHasErrors(['end_date']);
    }

    public function test_cannot_add_to_cart_without_subcategory_and_barang(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.asset-cart.store'), [
            'subcategory_id' => null,
            'barang_id' => null,
            'quantity' => 1,
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_cart_index_returns_zero_stock_without_querying_unit_or_lot(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        ConsumableBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('smart.asset-cart'));

        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Smart/User/RequestCart')
            ->has('cartItems', 1)
            ->where('cartItems.0.stock', 0)
        );
    }
}
