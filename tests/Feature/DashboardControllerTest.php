<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\AdmUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Controller Feature Tests
 *
 * Verifies admin inventory metric calculations (consumable/non-consumable stats) and authorization guards.
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_stats()
    {
        // 1. Setup Admin User
        $adminEmployee = \App\Models\HrdEmployee::factory()->create(['employee_id' => '255578']);
        $adminUser = AdmUser::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        // 2. Setup Consumable Data
        $consumableCat = Category::factory()->create(['is_consumable' => true, 'name' => 'Consumables']);
        $consumableSub = Subcategory::factory()->create(['category_id' => $consumableCat->id, 'name' => 'Stationery']);
        $consumableBarang = Barang::factory()->create(['subcategory_id' => $consumableSub->id]);
        
        Lot::factory()->create([
            'barang_id' => $consumableBarang->id,
            'current_quantity' => 10,
        ]);
        Lot::factory()->create([
            'barang_id' => $consumableBarang->id,
            'current_quantity' => 5,
        ]); // Total for Stationery should be 15

        // 3. Setup Non-Consumable Data for CFS
        $nonConsumableCat = Category::factory()->create(['is_consumable' => false, 'name' => 'Electronics']);
        $nonConsumableSub = Subcategory::factory()->create(['category_id' => $nonConsumableCat->id]);
        $nonConsumableBarang = Barang::factory()->create(['subcategory_id' => $nonConsumableSub->id]);
        
        $cfsOrganizer = Organizer::factory()->create(['name' => 'CFS']);
        $cfsLot = Lot::factory()->create([
            'barang_id' => $nonConsumableBarang->id,
            'organizer_id' => $cfsOrganizer->id,
        ]);
        
        Unit::factory()->create(['lot_id' => $cfsLot->id]);
        Unit::factory()->create(['lot_id' => $cfsLot->id]); // Total CFS units for Electronics should be 2

        // 4. Setup Non-Consumable Data for ICT
        $ictOrganizer = Organizer::factory()->create(['name' => 'ICT']);
        $ictLot = Lot::factory()->create([
            'barang_id' => $nonConsumableBarang->id,
            'organizer_id' => $ictOrganizer->id,
        ]);
        
        Unit::factory()->create(['lot_id' => $ictLot->id]); // Total ICT units for Electronics should be 1

        // 5. Act
        $response = $this->actingAs($adminUser)->get(route('smart.dashboard'));

        // 6. Assert
        $response->assertStatus(200);
        
        // Assert Inertia Props
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Admin/Dashboard')
            ->has('consumableSubcategoryStats', 1)
            ->where('consumableSubcategoryStats.0.subcategory_name', 'Stationery')
            ->where('consumableSubcategoryStats.0.total_quantity', fn ($val) => (int) $val === 15)
            ->has('cfsCategoryStats', 1)
            ->where('cfsCategoryStats.0.category_name', 'Electronics')
            ->where('cfsCategoryStats.0.total_units', 2)
            ->has('ictCategoryStats', 1)
            ->where('ictCategoryStats.0.category_name', 'Electronics')
            ->where('ictCategoryStats.0.total_units', 1)
        );
    }
    
    public function test_non_admin_forbidden_on_admin_dashboard()
    {
        config(['app.disable_test_admin_bypass' => true]);
        $user = AdmUser::factory()->create();
        
        $response = $this->actingAs($user)->get(route('smart.dashboard'));
        $response->assertForbidden();
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }
}
