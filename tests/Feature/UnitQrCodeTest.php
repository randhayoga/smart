<?php

namespace Tests\Feature;

use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitQrCodeTest extends TestCase
{
    use RefreshDatabase;

    private function createUnit()
    {
        $lot = Lot::factory()->create();
        
        $tipeCode = $lot->barang->subcategory->code ?? 'SUB';
        $organizerCode = $lot->organizer->name ?? 'ORG';
        $combination = "{$tipeCode}-{$organizerCode}-PTRE";
        $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');
        $unitNumber = "00001-{$combination}-{$yy}";
        if (strlen($unitNumber) > 25) {
            $unitNumber = substr($unitNumber, 0, 25);
        }

        return Unit::create([
            'number' => $unitNumber,
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'price' => $lot->unit_price,
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);
    }

    public function test_guest_cannot_access_qr_code(): void
    {
        $unit = $this->createUnit();

        $response = $this->get(route('smart.inventory.units.qr-code', $unit));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_access_qr_code(): void
    {
        $user = User::factory()->create();
        $unit = $this->createUnit();

        $response = $this->actingAs($user)->get(route('smart.inventory.units.qr-code', $unit));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertNotEmpty($response->getContent());
    }
}
