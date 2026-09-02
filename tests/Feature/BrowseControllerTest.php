<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\AdmUser as User;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Browse Controller Feature Tests
 *
 * Verifies catalog browsing, cart operations for consumable/non-consumable items, and checkout confirmation into requests.
 */
class BrowseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable the unit test admin bypass so we can test actual user behavior
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
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

        $response->assertRedirect();
        $response->assertSessionHas('success');

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

        $response->assertRedirect();
        $response->assertSessionHas('success');

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

    public function test_confirmation_page_only_includes_assigned_projects(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $rbs = \App\Models\TbRbs::create([
            'id' => 'P0',
            'name' => 'Anggota',
            'showing_name' => 'Anggota',
        ]);

        $projectAssigned = \App\Models\TbProject::factory()->create([
            'no_project' => 'PRJ-001',
            'project_name' => 'Assigned Project',
        ]);
        $projectUnassigned = \App\Models\TbProject::factory()->create([
            'no_project' => 'PRJ-002',
            'project_name' => 'Unassigned Project',
        ]);

        \App\Models\TbAssignProject::create([
            'npk' => $user->employee_id,
            'no_project' => $projectAssigned->no_project,
            'id_rbs' => $rbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        \App\Models\TbAssignProject::create([
            'npk' => $otherUser->employee_id,
            'no_project' => $projectUnassigned->no_project,
            'id_rbs' => $rbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('smart.asset-cart.confirmation'));

        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Smart/User/CartConfirmation')
            ->has('projects', 1)
            ->where('projects.0.value', (string) $projectAssigned->id)
            ->where('projects.0.label', '[PRJ-001] Assigned Project')
        );

        $borrowResponse = $this->actingAs($user)->get(route('smart.borrow-cart.confirmation'));

        $borrowResponse->assertStatus(200);
        $borrowResponse->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Smart/User/CartConfirmation')
            ->has('projects', 1)
            ->where('projects.0.value', (string) $projectAssigned->id)
            ->where('projects.0.label', '[PRJ-001] Assigned Project')
        );
    }

    public function test_corporate_submit_notifies_department_manager(): void
    {
        $user = User::factory()->create(['name' => 'John Requester']);
        $manager = User::factory()->create(['name' => 'Dept Manager']);

        $orgchart = \App\Models\HrdOrgchart::factory()->create([
            'org_name' => 'Departemen Operasional',
            'employee_id' => $manager->employee_id,
        ]);

        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = \App\Models\Cart\ConsumableBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->post(route('smart.asset-cart.confirmation.store'), [
            'items' => [['id' => $basketItem->id]],
            'pemanfaatan' => 'corporate',
            'departemen' => (string) $orgchart->id,
            'alasan' => 'Permintaan barang departemen',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $request = \App\Models\Request\Request::where('user_id', $user->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals($manager->id, $request->approver_id);

        $notification = $manager->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals("Permintaan Baru: {$request->request_number}", $notification->data['title']);
        $this->assertStringContainsString('John Requester', $notification->data['message']);
        $this->assertStringContainsString('Departemen Operasional', $notification->data['message']);
        $this->assertEquals('info', $notification->data['type']);
    }

    public function test_project_submit_notifies_newest_p2211_manager(): void
    {
        $user = User::factory()->create(['name' => 'Alice Requester']);
        $oldPM = User::factory()->create(['name' => 'Old PM']);
        $newPM = User::factory()->create(['name' => 'New PM']);

        $rbs = \App\Models\TbRbs::create([
            'id' => 'P2211',
            'name' => 'Project Manager',
            'showing_name' => 'Project Manager',
        ]);

        $project = \App\Models\TbProject::factory()->create([
            'no_project' => 'PRJ-999',
            'project_name' => 'Proyek Alpha',
        ]);

        // Old PM assignment
        \App\Models\TbAssignProject::create([
            'npk' => $oldPM->employee_id,
            'no_project' => $project->no_project,
            'id_rbs' => $rbs->id,
            'start_date' => '2025-01-01 00:00:00',
        ]);

        // Newest PM assignment
        \App\Models\TbAssignProject::create([
            'npk' => $newPM->employee_id,
            'no_project' => $project->no_project,
            'id_rbs' => $rbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $basketItem = \App\Models\Cart\AssetBasket::create([
            'user_id' => $user->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity' => 1,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
        ]);

        $response = $this->actingAs($user)->post(route('smart.borrow-cart.confirmation.store'), [
            'items' => [['id' => $basketItem->id]],
            'pemanfaatan' => 'project',
            'project' => (string) $project->id,
            'alasan' => 'Peminjaman alat proyek',
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $request = \App\Models\Request\Request::where('user_id', $user->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals($newPM->id, $request->approver_id);

        $notification = $newPM->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals("Peminjaman Baru: {$request->request_number}", $notification->data['title']);
        $this->assertStringContainsString('Alice Requester', $notification->data['message']);
        $this->assertStringContainsString('Proyek Alpha', $notification->data['message']);
        $this->assertEquals('info', $notification->data['type']);
        $this->assertNull($oldPM->notifications()->first());
    }
}
