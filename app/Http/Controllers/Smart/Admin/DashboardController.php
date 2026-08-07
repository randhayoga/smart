<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        // Fetch the total number of units of each non_consumable category that is under CFS organizer
        $cfsCategoryStats = \Illuminate\Support\Facades\DB::table('units')
            ->join('lots', 'units.lot_id', '=', 'lots.id')
            ->join('organizers', 'lots.organizer_id', '=', 'organizers.id')
            ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
            ->join('subcategories', 'barangs.subcategory_id', '=', 'subcategories.id')
            ->join('categories', 'subcategories.category_id', '=', 'categories.id')
            ->where('categories.is_consumable', false)
            ->where('organizers.name', 'CFS')
            ->groupBy('categories.id', 'categories.name')
            ->select('categories.name as category_name', \Illuminate\Support\Facades\DB::raw('COUNT(units.id) as total_units'))
            ->get();

        // Fetch the total number of units of each non_consumable category that is under ICT organizer
        $ictCategoryStats = \Illuminate\Support\Facades\DB::table('units')
            ->join('lots', 'units.lot_id', '=', 'lots.id')
            ->join('organizers', 'lots.organizer_id', '=', 'organizers.id')
            ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
            ->join('subcategories', 'barangs.subcategory_id', '=', 'subcategories.id')
            ->join('categories', 'subcategories.category_id', '=', 'categories.id')
            ->where('categories.is_consumable', false)
            ->where('organizers.name', 'ICT')
            ->groupBy('categories.id', 'categories.name')
            ->select('categories.name as category_name', \Illuminate\Support\Facades\DB::raw('COUNT(units.id) as total_units'))
            ->get();
        
        return Inertia::render('Smart/Admin/Dashboard', [
            'user' => $user,
            'consumableSubcategoryStats' => $consumableSubcategoryStats,
            'cfsCategoryStats' => $cfsCategoryStats,
            'ictCategoryStats' => $ictCategoryStats,
        ]);
    }
}
