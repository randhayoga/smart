<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Dashboard Controller computing inventory summaries, stock counts, and organizer statistics.
 */
class DashboardController extends Controller
{
    /**
     * Menampilkan halaman dashboard admin.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        
        // Redirect non-admin to user dashboard
        if (!$user->is_admin) {
            return redirect()->route('smart.user.dashboard');
        }

        // Fetch the total amount of current quantity of each consumable subcategory
        $consumableSubcategoryStats = \Illuminate\Support\Facades\DB::table('lots')
            ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
            ->join('subcategories', 'barangs.subcategory_id', '=', 'subcategories.id')
            ->join('categories', 'subcategories.category_id', '=', 'categories.id')
            ->where('categories.is_consumable', true)
            ->groupBy('subcategories.id', 'subcategories.name')
            ->select('subcategories.name as subcategory_name', \Illuminate\Support\Facades\DB::raw('SUM(lots.current_quantity) as total_quantity'))
            ->get();

        // Optimized Single Query: Fetch total number of units for non_consumable categories under CFS and ICT organizers
        $nonConsumableCategoryStats = \Illuminate\Support\Facades\DB::table('units')
            ->join('lots', 'units.lot_id', '=', 'lots.id')
            ->join('organizers', 'lots.organizer_id', '=', 'organizers.id')
            ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
            ->join('subcategories', 'barangs.subcategory_id', '=', 'subcategories.id')
            ->join('categories', 'subcategories.category_id', '=', 'categories.id')
            ->where('categories.is_consumable', false)
            ->whereIn('organizers.name', ['CFS', 'ICT'])
            ->groupBy('organizers.name', 'categories.id', 'categories.name')
            ->select(
                'organizers.name as organizer_name',
                'categories.name as category_name',
                \Illuminate\Support\Facades\DB::raw('COUNT(units.id) as total_units')
            )
            ->get();

        $cfsCategoryStats = $nonConsumableCategoryStats
            ->where('organizer_name', 'CFS')
            ->map(fn ($item) => [
                'category_name' => $item->category_name,
                'total_units' => (int) $item->total_units,
            ])
            ->values();

        $ictCategoryStats = $nonConsumableCategoryStats
            ->where('organizer_name', 'ICT')
            ->map(fn ($item) => [
                'category_name' => $item->category_name,
                'total_units' => (int) $item->total_units,
            ])
            ->values();
        
        return Inertia::render('Smart/Admin/Dashboard', [
            'user' => $user,
            'consumableSubcategoryStats' => $consumableSubcategoryStats,
            'cfsCategoryStats' => $cfsCategoryStats,
            'ictCategoryStats' => $ictCategoryStats,
        ]);
    }
}
