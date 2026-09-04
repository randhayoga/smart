<?php

namespace Tests\Unit;

use App\Models\AdmUser;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestHandover;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_request_fulfillment_for_asset_and_consumable(): void
    {
        $user = AdmUser::factory()->create();
        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create(['lot_id' => $lot->id]);

        $request = SmartRequest::create([
            'request_number' => '0926-0001',
            'user_id' => $user->id,
            'approver_id' => $user->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test reasoning',
            'status' => 'handover',
        ]);

        $requestItem = RequestItem::create([
            'request_id' => $request->id,
            'subcategory_id' => $subcategory->id,
            'barang_id' => $barang->id,
            'quantity_requested' => 1,
            'status' => 'fulfilled',
        ]);

        $handover = RequestHandover::create([
            'request_id' => $request->id,
            'method' => 'pickup',
            'scheduled_date' => now(),
            'location' => 'Ruang IFS',
            'is_auto_set' => true,
        ]);

        $return = RequestReturn::create([
            'request_id' => $request->id,
            'method' => 'pickup',
            'scheduled_date' => now()->addDays(7),
            'location' => 'Ruang IFS',
            'is_auto_set' => true,
        ]);

        $fulfillment = RequestFulfillment::create([
            'request_item_id' => $requestItem->id,
            'unit_id' => $unit->id,
            'lot_id' => $lot->id,
            'handover_id' => $handover->id,
            'return_id' => $return->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => now(),
            'completed_at' => null,
            'placement' => 'Lt. 2 IT Room',
        ]);

        $this->assertDatabaseHas('request_fulfillments', [
            'id' => $fulfillment->id,
            'placement' => 'Lt. 2 IT Room',
            'unit_id' => $unit->id,
            'lot_id' => $lot->id,
            'handover_id' => $handover->id,
            'return_id' => $return->id,
        ]);

        // Relationships on RequestFulfillment
        $this->assertInstanceOf(RequestItem::class, $fulfillment->requestItem);
        $this->assertEquals($requestItem->id, $fulfillment->requestItem->id);

        $this->assertInstanceOf(Unit::class, $fulfillment->unit);
        $this->assertEquals($unit->id, $fulfillment->unit->id);

        $this->assertInstanceOf(Lot::class, $fulfillment->lot);
        $this->assertEquals($lot->id, $fulfillment->lot->id);

        $this->assertInstanceOf(RequestHandover::class, $fulfillment->handover);
        $this->assertEquals($handover->id, $fulfillment->handover->id);

        $this->assertInstanceOf(RequestReturn::class, $fulfillment->return);
        $this->assertEquals($return->id, $fulfillment->return->id);

        // Inverse Relationships
        $this->assertTrue($requestItem->fulfillments->contains($fulfillment));
        $this->assertTrue($unit->fulfillments->contains($fulfillment));
        $this->assertTrue($lot->fulfillments->contains($fulfillment));
        $this->assertTrue($handover->fulfillments->contains($fulfillment));
        $this->assertTrue($return->fulfillments->contains($fulfillment));
    }
}
