<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_destroy_barang_without_lots(): void
    {
        $user = User::factory()->create();
        $barang = Barang::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.destroy', $barang));

        $response->assertRedirect(route('smart.inventory'));
        $response->assertSessionHas('success', 'Barang berhasil dihapus.');
        $this->assertDatabaseMissing('barangs', [
            'id' => $barang->id,
        ]);
    }

    public function test_cannot_destroy_barang_with_lots(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $barang = $lot->barang;

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.destroy', $barang));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Barang tidak dapat dihapus karena masih memiliki LOT terkait.');
        $this->assertDatabaseHas('barangs', [
            'id' => $barang->id,
        ]);
    }
}
