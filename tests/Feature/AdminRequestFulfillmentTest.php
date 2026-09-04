<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Room;
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

    private function createAdmin(): AdmUser
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        return AdmUser::factory()->create(['employee_id' => $employee->employee_id]);
    }

    private function createRequester(): AdmUser
    {
        return AdmUser::factory()->create(['name' => 'John Doe Requester']);
    }

    private function createManager(): AdmUser
    {
        $managerUser = AdmUser::factory()->create(['name' => 'Jane Doe Manager']);
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
        $this->get(route('smart.fulfillment.index'))->assertRedirectContains('/auth/login');
        $this->get(route('smart.partial.index'))->assertRedirectContains('/auth/login');

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

        // Viewing show page triggers auto-fulfillment
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
        $floor = Floor::factory()->create(['location_id' => $loc->id, 'name' => 'Lantai 1']);
        $room = Room::factory()->create(['floor_id' => $floor->id, 'name' => 'Ruang ATK']);

        $oldLot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'number' => 'LOT-OLD-01',
            'current_quantity' => 10,
            'date_of_receipt' => now()->subDays(30),
            'location_id' => $loc->id,
            'floor_id' => $floor->id,
            'room_id' => $room->id,
        ]);

        $newLot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'number' => 'LOT-NEW-02',
            'current_quantity' => 20,
            'date_of_receipt' => now()->subDays(5),
            'location_id' => $loc->id,
            'floor_id' => $floor->id,
            'room_id' => $room->id,
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

        // Viewing show page auto-fulfills 10 from old lot and 5 from new lot
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
            'assigned_at' => now(),
        ]);

        // Confirm full assignment
        $response = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => false,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('full', $response->json('status'));

        $req->refresh();
        $this->assertEquals('confirm', $req->status);

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'partial',
            'status_to' => 'confirm',
            'changed_by' => $admin->id,
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
            'assigned_at' => now(),
        ]);

        // 1. Without allow_partial flag -> 422 validation error
        $failResponse = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => false,
            ]);

        $failResponse->assertStatus(422);

        // 2. With allow_partial = true -> transitions to 'partial'
        $successResponse = $this->actingAs($admin)
            ->postJson(route('smart.fulfillment.confirm', $req->uuid), [
                'allow_partial' => true,
            ]);

        $successResponse->assertStatus(200);
        $this->assertEquals('partial', $successResponse->json('status'));

        $req->refresh();
        $this->assertEquals('partial', $req->status);

        $this->assertDatabaseHas('request_status_logs', [
            'request_id' => $req->id,
            'status_from' => 'confirm',
            'status_to' => 'partial',
            'changed_by' => $admin->id,
        ]);
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
}
