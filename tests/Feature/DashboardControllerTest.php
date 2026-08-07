<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_stats()
    {
        // 1. Setup Admin User
        $adminUser = User::factory()->create(['is_admin' => true]);

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
        $response = $this->actingAs($adminUser)->get(route('smart.admin.dashboard'));

        // 6. Assert
        $response->assertStatus(200);
        
        // Assert Inertia Props
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Admin/Dashboard')
            ->has('consumableSubcategoryStats', 1)
            ->where('consumableSubcategoryStats.0.subcategory_name', 'Stationery')
            ->where('consumableSubcategoryStats.0.total_quantity', "15") // DB SUM can return string depending on driver
            ->has('cfsCategoryStats', 1)
            ->where('cfsCategoryStats.0.category_name', 'Electronics')
            ->where('cfsCategoryStats.0.total_units', 2)
            ->has('ictCategoryStats', 1)
            ->where('ictCategoryStats.0.category_name', 'Electronics')
            ->where('ictCategoryStats.0.total_units', 1)
        );
    }
    
    public function test_non_admin_redirected_to_user_dashboard()
    {
        $user = User::factory()->create(['is_admin' => false]);
        
        $response = $this->actingAs($user)->get(route('smart.admin.dashboard'));
        
        $response->assertRedirect(route('smart.user.dashboard'));
    }
}
