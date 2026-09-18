<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Subcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Feature tests for InventoryAuditController managing the inventory audit trail page.
 */
class InventoryAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create();
    }

    private function createConsumableBarang(): Barang
    {
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        return Barang::factory()->create(['subcategory_id' => $subcategory->id]);
    }

    private function createLot(Barang $barang, int $quantity = 25): Lot
    {
        $location = Location::firstOrCreate(['name' => 'Gudang Utama'], ['full_name' => 'Gudang Utama']);
        return Lot::factory()->create([
            'barang_id' => $barang->id,
            'location_id' => $location->id,
            'initial_quantity' => $quantity,
            'current_quantity' => $quantity,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_inventory_audit_page(): void
    {
        $response = $this->get(route('smart.audit-stok'));
        $response->assertRedirect();
    }

    public function test_admin_can_access_inventory_audit_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('smart.audit-stok'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/AuditManajemenStok')
            ->has('logs')
        );
    }

    public function test_ifs_manager_can_access_inventory_audit_page(): void
    {
        config(['app.disable_test_admin_bypass' => true]);

        $ifsManager = User::factory()->create();
        $ifsEmployee = \App\Models\HrdEmployee::where('employee_id', $ifsManager->employee_id)->first();
        $ifsOrgchart = \App\Models\HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrgchart->update([
            'employee_id' => $ifsManager->employee_id,
            'org_code' => 'IFS',
        ]);
        $ifsManager->refresh();

        $response = $this->actingAs($ifsManager)->get(route('smart.audit-stok'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/AuditManajemenStok')
            ->has('logs')
        );

        config(['app.disable_test_admin_bypass' => false]);
    }

    public function test_regular_user_cannot_access_inventory_audit_page(): void
    {
        config(['app.disable_test_admin_bypass' => true]);

        $user = User::factory()->create();
        $this->assertEquals('user', $user->role);

        $response = $this->actingAs($user)->get(route('smart.audit-stok'));

        $response->assertForbidden();

        config(['app.disable_test_admin_bypass' => false]);
    }

    public function test_inventory_audit_returns_mapped_logs_with_barang_lot_and_actor_data(): void
    {
        $admin = $this->createAdmin();
        $actor = User::factory()->create(['name' => 'John Doe']);
        $barang = $this->createConsumableBarang();
        $lot = $this->createLot($barang, 50);

        $log = InventoryLog::factory()->create([
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'user_id' => $actor->id,
            'action_type' => 'stock_in',
            'quantity_change' => 50,
            'note' => 'Penerimaan batch baru',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('smart.audit-stok'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/AuditManajemenStok')
            ->has('logs', 1)
            ->where('logs.0.id', $log->id)
            ->where('logs.0.barang_number', $barang->number)
            ->where('logs.0.barang_name', $barang->name)
            ->where('logs.0.lot_number', $lot->number)
            ->where('logs.0.action_type', 'stock_in')
            ->where('logs.0.quantity_change', 50)
            ->where('logs.0.actor', 'John Doe')
            ->where('logs.0.note', 'Penerimaan batch baru')
        );
    }

    public function test_inventory_audit_falls_back_to_previous_state_when_barang_deleted(): void
    {
        $admin = $this->createAdmin();
        $actor = User::factory()->create(['name' => 'Jane Doe']);

        $log = InventoryLog::factory()->create([
            'barang_id' => null,
            'lot_id' => null,
            'user_id' => $actor->id,
            'action_type' => 'delete',
            'quantity_change' => 0,
            'previous_state' => [
                'id' => 999,
                'number' => 'DEL-001',
                'name' => 'Barang Terhapus',
            ],
            'note' => 'Penghapusan tipe barang',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('smart.audit-stok'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/AuditManajemenStok')
            ->has('logs', 1)
            ->where('logs.0.barang_number', 'DEL-001')
            ->where('logs.0.barang_name', 'Barang Terhapus')
            ->where('logs.0.lot_number', '-')
            ->where('logs.0.action_type', 'delete')
            ->where('logs.0.actor', 'Jane Doe')
        );
    }
}
