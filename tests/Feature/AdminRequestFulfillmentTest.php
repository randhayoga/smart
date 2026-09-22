<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Admin Request Fulfillment (Unit Assignment & Lot Allocation) workflows.
 */
class AdminRequestFulfillmentTest extends TestCase
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

    private function createAdmin(): User
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '999998']);
        return User::factory()->create(['employee_id' => $employee->employee_id]);
    }

    private function createRequester(): User
    {
        return User::factory()->create(['name' => 'John Doe Requester']);
    }

    private function createManager(): User
    {
        $managerUser = User::factory()->create(['name' => 'Jane Doe Manager']);
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);
        return $managerUser;
    }

    public function test_only_admin_can_access_fulfillment_routes(): void
    {
        $user = $this->createRequester();
        $manager = $this->createManager();
        $admin = $this->createAdmin();

        $req = SmartRequest::create([
            'request_number' => '0926-0001',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        // 1. Unauthenticated gets redirected
        $this->get(route('smart.fulfillment.index'))->assertRedirectContains(route('login'));
        $this->get(route('smart.partial.index'))->assertRedirectContains(route('login'));

        // 2. Regular user gets 403
        $this->actingAs($user)->get(route('smart.fulfillment.index'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.partial.index'))->assertStatus(403);
        $this->actingAs($user)->get(route('smart.fulfillment.show', $req->uuid))->assertStatus(403);

        // 3. Manager gets 403
        $this->actingAs($manager)->get(route('smart.fulfillment.index'))->assertStatus(403);
        $this->actingAs($manager)->get(route('smart.partial.index'))->assertStatus(403);

        // 4. Admin gets 200
        $this->actingAs($admin)->get(route('smart.fulfillment.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('smart.partial.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('smart.fulfillment.show', $req->uuid))->assertStatus(200);
    }

    public function test_page_separation_between_confirmed_and_partial_requests(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $confirmedReq = SmartRequest::create([
            'request_number' => '0926-0002',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        $partialReq = SmartRequest::create([
            'request_number' => '0926-0003',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'partial',
        ]);

        $waitingReq = SmartRequest::create([
            'request_number' => '0926-0004',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'wait',
        ]);

        // 1. Confirmed page shows only 'confirm'
        $confirmedResponse = $this->actingAs($admin)
            ->getJson(route('smart.fulfillment.index'))
            ->assertStatus(200);

        $confirmedData = collect($confirmedResponse->json('requests'));
        $this->assertTrue($confirmedData->contains('number', '0926-0002'));
        $this->assertFalse($confirmedData->contains('number', '0926-0003'));
        $this->assertFalse($confirmedData->contains('number', '0926-0004'));

        // 2. Partial page shows only 'partial'
        $partialResponse = $this->actingAs($admin)
            ->getJson(route('smart.partial.index'))
            ->assertStatus(200);

        $partialData = collect($partialResponse->json('requests'));
        $this->assertTrue($partialData->contains('number', '0926-0003'));
        $this->assertFalse($partialData->contains('number', '0926-0002'));
        $this->assertFalse($partialData->contains('number', '0926-0004'));
    }

    public function test_fifo_auto_fulfillment_for_assets(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);

        // Create 3 lots with different receipt dates
        $oldLot = Lot::factory()->create(['barang_id' => $barang->id, 'date_of_receipt' => now()->subDays(20)]);
        $midLot = Lot::factory()->create(['barang_id' => $barang->id, 'date_of_receipt' => now()->subDays(10)]);
        $newLot = Lot::factory()->create(['barang_id' => $barang->id, 'date_of_receipt' => now()->subDays(2)]);

        $oldUnit = Unit::factory()->create(['lot_id' => $oldLot->id, 'status' => 'Tersedia', 'number' => 'AST-OLD-01']);
        $midUnit = Unit::factory()->create(['lot_id' => $midLot->id, 'status' => 'Tersedia', 'number' => 'AST-MID-02']);
        $newUnit = Unit::factory()->create(['lot_id' => $newLot->id, 'status' => 'Tersedia', 'number' => 'AST-NEW-03']);

        $req = SmartRequest::create([
            'request_number' => '0926-0005',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 2,
        ]);

        // Explicitly trigger FIFO auto-fulfillment service
        app(\App\Services\RequestFulfillmentService::class)->autoFulfillRequest($req);

        $response = $this->actingAs($admin)
            ->getJson(route('smart.fulfillment.show', $req->uuid))
            ->assertStatus(200);

        // The 2 oldest units should be assigned (AST-OLD-01 and AST-MID-02)
        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item->id,
            'unit_id' => $oldUnit->id,
        ]);
        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item->id,
            'unit_id' => $midUnit->id,
        ]);
        $this->assertDatabaseMissing('request_fulfillments', [
            'request_item_id' => $item->id,
            'unit_id' => $newUnit->id,
        ]);

        // Assert JSON contains slots and colors
        $itemJson = $response->json('request.items.0');
        $this->assertCount(2, $itemJson['allocation_slots']);
        $this->assertEquals('AST-OLD-01', $itemJson['allocation_slots'][0]['asset_number']);
        $this->assertEquals('purple', $itemJson['allocation_slots'][0]['color']);
        $this->assertEquals('AST-MID-02', $itemJson['allocation_slots'][1]['asset_number']);
        $this->assertEquals('purple', $itemJson['allocation_slots'][1]['color']);

        // Requester name must be present
        $this->assertEquals($user->name, $response->json('request.requester'));
    }

    public function test_fifo_auto_fulfillment_for_consumables(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id, 'name' => 'Kertas A4']);
        $brand = Brand::factory()->create(['name' => 'PaperOne']);
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'name' => 'Kertas A4 80gr',
            'specification' => 'Rim isi 500 lembar',
        ]);

        $loc = Location::factory()->create(['name' => 'Gudang Utama']);

        $oldLot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'number' => 'LOT-OLD-01',
            'current_quantity' => 10,
            'date_of_receipt' => now()->subDays(30),
            'location_id' => $loc->id,
        ]);

        $newLot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'number' => 'LOT-NEW-02',
            'current_quantity' => 20,
            'date_of_receipt' => now()->subDays(5),
            'location_id' => $loc->id,
        ]);

        $req = SmartRequest::create([
            'request_number' => '0926-0006',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 15,
        ]);

        // Explicitly trigger FIFO auto-fulfillment service
        app(\App\Services\RequestFulfillmentService::class)->autoFulfillRequest($req);

        $response = $this->actingAs($admin)
            ->getJson(route('smart.fulfillment.show', $req->uuid))
            ->assertStatus(200);

        $itemJson = $response->json('request.items.0');
        $this->assertTrue($itemJson['is_consumable']);
        $this->assertCount(2, $itemJson['lot_fulfillments']);

        $lot1 = $itemJson['lot_fulfillments'][0];
        $this->assertEquals('LOT-OLD-01', $lot1['lot_number']);
        $this->assertEquals(10, $lot1['quantity_fulfilled']);
        $this->assertEquals('PaperOne', $lot1['brand_name']);
        $this->assertStringContainsString('Gudang Utama', $lot1['storage_location']);

        $lot2 = $itemJson['lot_fulfillments'][1];
        $this->assertEquals('LOT-NEW-02', $lot2['lot_number']);
        $this->assertEquals(5, $lot2['quantity_fulfilled']);

        $this->assertEquals(15, $itemJson['consumable_summary']['quantity_fulfilled']);
        $this->assertTrue($itemJson['consumable_summary']['is_fully_fulfilled']);
    }

    public function test_manual_unit_assignment_and_modal_datatable(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        $unit1 = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-U1']);
        $unit2 = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-U2']);
        $unit3 = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-U3']);

        $req = SmartRequest::create([
            'request_number' => '0926-0007',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 2,
        ]);

        // Override assignment manually with unit 2 and unit 3
        $assignResponse = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.items.assign', $item->id), [
                'unit_ids' => [$unit2->id, $unit3->id],
            ]);

        $assignResponse->assertStatus(200);

        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item->id,
            'unit_id' => $unit2->id,
        ]);
        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item->id,
            'unit_id' => $unit3->id,
        ]);
        $this->assertDatabaseMissing('request_fulfillments', [
            'request_item_id' => $item->id,
            'unit_id' => $unit1->id,
        ]);

        // Exceeding requested quantity fails validation
        $exceedResponse = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.items.assign', $item->id), [
                'unit_ids' => [$unit1->id, $unit2->id, $unit3->id],
            ]);

        $exceedResponse->assertStatus(422);
    }

    public function test_full_fulfillment_confirmation_flow(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-FULL-01']);

        $req = SmartRequest::create([
            'request_number' => '0926-0008',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'partial',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
        ]);

        RequestFulfillment::create([
            'request_item_id' => $item->id,
            'unit_id' => $unit->id,
            'lot_id' => $lot->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => null,
        ]);

        // Confirm full assignment
        $response = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => false,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('full', $response->json('status'));

        $req->refresh();
        $this->assertEquals('menunggu_serah_terima', $req->status);

        $rf = RequestFulfillment::where('request_item_id', $item->id)->first();
        $this->assertNotNull($rf->assigned_at);
        $this->assertNull($rf->confirmed_at);

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'partial',
            'status_to' => 'menunggu_serah_terima',
            'changed_by' => $admin->id,
            'note' => 'Admin telah mengalokasikan barang secara penuh',
        ]);
    }

    public function test_partial_fulfillment_confirmation_flow(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-PART-01']);

        $req = SmartRequest::create([
            'request_number' => '0926-0009',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        // Requested 3 units, only 1 assigned
        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 3,
        ]);

        RequestFulfillment::create([
            'request_item_id' => $item->id,
            'unit_id' => $unit->id,
            'lot_id' => $lot->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => null,
        ]);

        // 1. Without allow_partial flag -> 422 validation error
        $failResponse = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => false,
            ]);

        $failResponse->assertStatus(422);

        // 2. With allow_partial = true -> transitions to 'menunggu_serah_terima,partial'
        $successResponse = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => true,
            ]);

        $successResponse->assertStatus(200);
        $this->assertEquals('partial', $successResponse->json('status'));

        $req->refresh();
        $this->assertEquals('menunggu_serah_terima,partial', $req->status);
        $this->assertTrue($req->hasStatus('menunggu_serah_terima'));
        $this->assertTrue($req->hasStatus('partial'));

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'confirm',
            'status_to' => 'menunggu_serah_terima,partial',
            'changed_by' => $admin->id,
            'note' => 'Admin telah mengalokasikan barang secara parsial',
        ]);
    }

    public function test_subsequent_full_allocation_of_partial_request_removes_partial_status(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        $unit1 = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-SUB-01']);
        $unit2 = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-SUB-02']);

        $req = SmartRequest::create([
            'request_number' => '0926-SUB1',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test subsequent fulfillment',
            'status' => 'menunggu_serah_terima,partial',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 2,
        ]);

        // 1st unit already confirmed
        RequestFulfillment::create([
            'request_item_id' => $item->id,
            'unit_id' => $unit1->id,
            'lot_id' => $lot->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => null,
        ]);

        // Assign 2nd unit (now 2/2 fulfilled)
        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign', $item->id), [
            'unit_ids' => [$unit1->id, $unit2->id],
        ])->assertStatus(200);

        // Confirm full fulfillment
        $response = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => false,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('full', $response->json('status'));

        $req->refresh();
        $this->assertEquals('menunggu_serah_terima', $req->status);
        $this->assertTrue($req->hasStatus('menunggu_serah_terima'));
        $this->assertFalse($req->hasStatus('partial'));

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'menunggu_serah_terima,partial',
            'status_to' => 'menunggu_serah_terima',
            'changed_by' => $admin->id,
            'note' => 'Admin telah mengalokasikan barang secara penuh',
        ]);
    }

    public function test_tab_visibility_for_new_partial_and_full_requests(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia']);

        // 1. Newly confirmed request (0 confirmed units) -> Perlu Alokasi ONLY
        $newlyConfirmed = SmartRequest::create([
            'request_number' => '0926-TNEW',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test new',
            'status' => 'confirm',
        ]);
        RequestItem::create([
            'request_id' => $newlyConfirmed->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
        ]);

        // 2. Partial request -> Parsial AND Serah Terima tabs, NOT Perlu Alokasi
        $partialReq = SmartRequest::create([
            'request_number' => '0926-TPRT',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test part',
            'status' => 'menunggu_serah_terima,partial',
        ]);
        $partItem = RequestItem::create([
            'request_id' => $partialReq->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 2,
        ]);
        RequestFulfillment::create([
            'request_item_id' => $partItem->id,
            'unit_id' => $unit->id,
            'lot_id' => $lot->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'confirmed_at' => null,
        ]);

        // 3. Fully fulfilled request -> Serah Terima tab ONLY
        $fullReq = SmartRequest::create([
            'request_number' => '0926-TFUL',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test full',
            'status' => 'menunggu_serah_terima',
        ]);

        $controller = app(\App\Http\Controllers\Smart\Admin\AdminActiveRequestController::class);

        $confirmedList = collect($controller->getConfirmedRequests());
        $partialList = collect($controller->getPartialRequests());
        $handoverList = collect($controller->getHandovers());

        // Perlu Alokasi tab assertions
        $this->assertTrue($confirmedList->contains('number', '0926-TNEW'));
        $this->assertFalse($confirmedList->contains('number', '0926-TPRT'));
        $this->assertFalse($confirmedList->contains('number', '0926-TFUL'));

        // Parsial tab assertions
        $this->assertTrue($partialList->contains('number', '0926-TPRT'));
        $this->assertFalse($partialList->contains('number', '0926-TNEW'));
        $this->assertFalse($partialList->contains('number', '0926-TFUL'));

        // Serah Terima tab assertions
        $this->assertTrue($handoverList->contains('number', '0926-TPRT'));
        $this->assertTrue($handoverList->contains('number', '0926-TFUL'));
        $this->assertFalse($handoverList->contains('number', '0926-TNEW'));
    }

    public function test_zero_allocation_fails_partial_confirmation(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $req = SmartRequest::create([
            'request_number' => '0926-0010',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reason',
            'status' => 'confirm',
        ]);

        RequestItem::create([
            'request_id' => $req->id,
            'quantity_requested' => 2,
        ]);

        // Zero units assigned with allow_partial = true must be rejected
        $response = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_active_requests_count_shared_to_inertia_badge_excluding_borrowed(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        // Active requests that MUST be counted in badge (approve, confirm, partial, handover, return)
        $includedStatuses = ['approve', 'confirm', 'partial', 'handover', 'return'];
        foreach ($includedStatuses as $idx => $status) {
            SmartRequest::create([
                'request_number' => '0926-INC-' . $idx,
                'user_id' => $user->id,
                'approver_id' => $manager->id,
                'utilization' => 'corporate',
                'reasoning' => 'Included status ' . $status,
                'status' => $status,
            ]);
        }

        // Requests that MUST NOT be counted (borrow is explicitly excluded, plus wait and archived statuses)
        $excludedStatuses = ['borrow', 'wait', 'success', 'reject', 'cancel'];
        foreach ($excludedStatuses as $idx => $status) {
            SmartRequest::create([
                'request_number' => '0926-EXC-' . $idx,
                'user_id' => $user->id,
                'approver_id' => $manager->id,
                'utilization' => 'corporate',
                'reasoning' => 'Excluded status ' . $status,
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('smart.requests.index'));
        $response->assertStatus(200);

        // Verify that Inertia shared auth.activeRequestsCount equals count of included requests (5)
        $response->assertInertia(fn ($page) => 
            $page->has('auth.activeRequestsCount')
                ->where('auth.activeRequestsCount', 5)
                ->where('auth.pendingAdminApprovedCount', 1)
        );
    }

    public function test_manual_lot_assignment_for_consumables(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot1 = Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 20]);
        $lot2 = Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 15]);

        $req = SmartRequest::create([
            'request_number' => '0926-LOT-01',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Consumable test',
            'status' => 'confirm',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 10,
        ]);

        // Assign 4 from lot1 and 6 from lot2
        $response = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.items.assign-lots', $item->id), [
                'lot_allocations' => [
                    ['lot_id' => $lot1->id, 'quantity' => 4],
                    ['lot_id' => $lot2->id, 'quantity' => 6],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item->id,
            'lot_id' => $lot1->id,
            'unit_id' => null,
            'quantity_fulfilled' => 4,
        ]);

        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item->id,
            'lot_id' => $lot2->id,
            'unit_id' => null,
            'quantity_fulfilled' => 6,
        ]);
    }

    public function test_manual_lot_assignment_cannot_exceed_available_or_requested_stock(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 5]);

        $req = SmartRequest::create([
            'request_number' => '0926-LOT-02',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Consumable limit test',
            'status' => 'confirm',
        ]);

        $item = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 10,
        ]);

        // Exceeds lot available stock (lot only has 5, request asks 8) -> 422
        $res1 = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.items.assign-lots', $item->id), [
                'lot_allocations' => [
                    ['lot_id' => $lot->id, 'quantity' => 8],
                ],
            ]);
        $res1->assertStatus(422);

        // Exceeds item requested quantity (item requested 10, sum is 12) -> 422
        $lot2 = Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 20]);
        $res2 = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.items.assign-lots', $item->id), [
                'lot_allocations' => [
                    ['lot_id' => $lot->id, 'quantity' => 5],
                    ['lot_id' => $lot2->id, 'quantity' => 7],
                ],
            ]);
        $res2->assertStatus(422);
    }

    public function test_resource_includes_variant_and_available_lots_for_modal(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createRequester();
        $manager = $this->createManager();

        // 1. Non-consumable non-specific item with brand
        $catNonConsumable = Category::factory()->create(['is_consumable' => false]);
        $subNonConsumable = Subcategory::factory()->create(['category_id' => $catNonConsumable->id, 'name' => 'Laptop']);
        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $barangAsset = Barang::factory()->create([
            'subcategory_id' => $subNonConsumable->id,
            'brand_id' => $brand->id,
            'name' => 'ThinkPad T14',
            'specification' => 'Core i7 16GB',
        ]);
        $lotAsset = Lot::factory()->create(['barang_id' => $barangAsset->id]);
        $unit = Unit::factory()->create(['lot_id' => $lotAsset->id, 'status' => 'Tersedia', 'number' => 'AST-VAR-01']);

        // 2. Consumable item
        $catConsumable = Category::factory()->create(['is_consumable' => true]);
        $subConsumable = Subcategory::factory()->create(['category_id' => $catConsumable->id, 'name' => 'Kertas']);
        $barangConsumable = Barang::factory()->create([
            'subcategory_id' => $subConsumable->id,
            'name' => 'HVS A4 80gr',
        ]);
        $lotConsumable = Lot::factory()->create([
            'barang_id' => $barangConsumable->id,
            'number' => 'LOT-HVS-01',
            'current_quantity' => 50,
        ]);

        $req = SmartRequest::create([
            'request_number' => '0926-RES-01',
            'user_id' => $user->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Resource test',
            'status' => 'confirm',
        ]);

        // Item 1: Non-specific asset (no barang_id)
        $item1 = RequestItem::create([
            'request_id' => $req->id,
            'subcategory_id' => $subNonConsumable->id,
            'quantity_requested' => 1,
        ]);

        // Item 2: Consumable (with barang_id)
        $item2 = RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barangConsumable->id,
            'subcategory_id' => $subConsumable->id,
            'quantity_requested' => 5,
        ]);

        $response = $this->actingAs($admin)->getJson(route('smart.fulfillment.show', $req->uuid));
        $response->assertStatus(200);

        $items = $response->json('request.items');
        $this->assertCount(2, $items);

        // Check Item 1 available_units has variant
        $units = $items[0]['available_units'];
        $this->assertNotEmpty($units);
        $this->assertStringContainsString('Lenovo', $units[0]['variant']);
        $this->assertStringContainsString('ThinkPad T14', $units[0]['variant']);

        // Check Item 2 available_lots
        $lots = $items[1]['available_lots'];
        $this->assertNotEmpty($lots);
        $this->assertEquals('LOT-HVS-01', $lots[0]['lot_code']);
        $this->assertEquals(50, $lots[0]['current_quantity']);
    }

    public function test_unit_can_be_staged_on_multiple_unconfirmed_requests_concurrently(): void
    {
        $admin = $this->createAdmin();
        $user1 = $this->createRequester();
        $user2 = User::factory()->create(['name' => 'VIP Requester']);
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-SHARED-01']);

        $req1 = SmartRequest::create([
            'request_number' => '0926-ST01',
            'user_id' => $user1->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test staging 1',
            'status' => 'confirm',
        ]);
        $item1 = RequestItem::create([
            'request_id' => $req1->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
        ]);

        $req2 = SmartRequest::create([
            'request_number' => '0926-ST02',
            'user_id' => $user2->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test staging 2',
            'status' => 'confirm',
        ]);
        $item2 = RequestItem::create([
            'request_id' => $req2->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
        ]);

        // 1. Assign unit to Request 1
        $res1 = $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign', $item1->id), [
            'unit_ids' => [$unit->id],
        ]);
        $res1->assertStatus(200);

        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item1->id,
            'unit_id' => $unit->id,
            'assigned_at' => null,
            'confirmed_at' => null,
        ]);

        // 2. Request 2 can also see and assign Unit (staged concurrently)
        $res2 = $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign', $item2->id), [
            'unit_ids' => [$unit->id],
        ]);
        $res2->assertStatus(200);

        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item2->id,
            'unit_id' => $unit->id,
            'assigned_at' => null,
            'confirmed_at' => null,
        ]);
    }

    public function test_confirming_request_locks_unit_and_evicts_it_from_competing_unconfirmed_requests(): void
    {
        $admin = $this->createAdmin();
        $user1 = $this->createRequester();
        $user2 = User::factory()->create(['name' => 'VIP Requester']);
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia', 'number' => 'AST-EVICT-01']);

        $req1 = SmartRequest::create([
            'request_number' => '0926-EV01',
            'user_id' => $user1->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test evict 1',
            'status' => 'confirm',
        ]);
        $item1 = RequestItem::create([
            'request_id' => $req1->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
        ]);

        $req2 = SmartRequest::create([
            'request_number' => '0926-EV02',
            'user_id' => $user2->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test evict 2',
            'status' => 'confirm',
        ]);
        $item2 = RequestItem::create([
            'request_id' => $req2->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 1,
        ]);

        // Both stage the same unit
        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign', $item1->id), [
            'unit_ids' => [$unit->id],
        ])->assertStatus(200);

        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign', $item2->id), [
            'unit_ids' => [$unit->id],
        ])->assertStatus(200);

        // Confirm Request 2 (the priority request)
        $confirmRes = $this->actingAs($admin)->postJson(route('smart.fulfillment.confirm', $req2->uuid), [
            'allow_partial' => false,
        ]);
        $confirmRes->assertStatus(200);

        // Request 2 now has assigned_at set, confirmed_at remains null
        $rf2 = RequestFulfillment::where('request_item_id', $item2->id)->where('unit_id', $unit->id)->first();
        $this->assertNotNull($rf2);
        $this->assertNotNull($rf2->assigned_at);
        $this->assertNull($rf2->confirmed_at);

        // Request 1's duplicate fulfillment must be completely evicted (deleted)
        $this->assertDatabaseMissing('request_fulfillments', [
            'request_item_id' => $item1->id,
            'unit_id' => $unit->id,
        ]);

        // Attempting to assign Unit to Request 1 now fails with 422 because it's locked by Request 2
        $reassignRes = $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign', $item1->id), [
            'unit_ids' => [$unit->id],
        ]);
        $reassignRes->assertStatus(422);
    }

    public function test_confirming_consumable_lot_deducts_stock_and_evicts_depleted_unconfirmed_requests(): void
    {
        $admin = $this->createAdmin();
        $user1 = $this->createRequester();
        $user2 = User::factory()->create(['name' => 'VIP Requester']);
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 10]);

        $req1 = SmartRequest::create([
            'request_number' => '0926-LE01',
            'user_id' => $user1->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test lot evict 1',
            'status' => 'confirm',
        ]);
        $item1 = RequestItem::create([
            'request_id' => $req1->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 10,
        ]);

        $req2 = SmartRequest::create([
            'request_number' => '0926-LE02',
            'user_id' => $user2->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test lot evict 2',
            'status' => 'confirm',
        ]);
        $item2 = RequestItem::create([
            'request_id' => $req2->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 10,
        ]);

        // Both stage all 10 items from lot
        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign-lots', $item1->id), [
            'lot_allocations' => [
                ['lot_id' => $lot->id, 'quantity' => 10],
            ],
        ])->assertStatus(200);

        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign-lots', $item2->id), [
            'lot_allocations' => [
                ['lot_id' => $lot->id, 'quantity' => 10],
            ],
        ])->assertStatus(200);

        // Confirm Request 2 (consumes all 10 stock)
        $this->actingAs($admin)->postJson(route('smart.fulfillment.confirm', $req2->uuid), [
            'allow_partial' => false,
        ])->assertStatus(200);

        // Stock must now be 0
        $lot->refresh();
        $this->assertEquals(0, $lot->current_quantity);

        // Request 1 fulfillment must be deleted because stock dropped to 0
        $this->assertDatabaseMissing('request_fulfillments', [
            'request_item_id' => $item1->id,
            'lot_id' => $lot->id,
        ]);
    }

    public function test_confirming_consumable_lot_clamps_partially_depleted_unconfirmed_requests(): void
    {
        $admin = $this->createAdmin();
        $user1 = $this->createRequester();
        $user2 = User::factory()->create(['name' => 'VIP Requester']);
        $manager = $this->createManager();

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $sub->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 10]);

        $req1 = SmartRequest::create([
            'request_number' => '0926-LC01',
            'user_id' => $user1->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test lot clamp 1',
            'status' => 'confirm',
        ]);
        $item1 = RequestItem::create([
            'request_id' => $req1->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 10,
        ]);

        $req2 = SmartRequest::create([
            'request_number' => '0926-LC02',
            'user_id' => $user2->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test lot clamp 2',
            'status' => 'confirm',
        ]);
        $item2 = RequestItem::create([
            'request_id' => $req2->id,
            'barang_id' => $barang->id,
            'subcategory_id' => $sub->id,
            'quantity_requested' => 10,
        ]);

        // Request 1 stages 8 items, Request 2 stages 6 items
        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign-lots', $item1->id), [
            'lot_allocations' => [
                ['lot_id' => $lot->id, 'quantity' => 8],
            ],
        ])->assertStatus(200);

        $this->actingAs($admin)->postJson(route('smart.fulfillment.items.assign-lots', $item2->id), [
            'lot_allocations' => [
                ['lot_id' => $lot->id, 'quantity' => 6],
            ],
        ])->assertStatus(200);

        // Confirm Request 2 with 6 items
        $this->actingAs($admin)->postJson(route('smart.fulfillment.confirm', $req2->uuid), [
            'allow_partial' => true,
        ])->assertStatus(200);

        // Stock dropped to 4 (10 - 6)
        $lot->refresh();
        $this->assertEquals(4, $lot->current_quantity);

        // Request 1 had 8 staged, but only 4 remain -> clamped to 4!
        $this->assertDatabaseHas('request_fulfillments', [
            'request_item_id' => $item1->id,
            'lot_id' => $lot->id,
            'quantity_fulfilled' => 4,
            'assigned_at' => null,
            'confirmed_at' => null,
        ]);
    }
}

