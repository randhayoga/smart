<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Dashboard Controller computing inventory summaries, stock counts, and organizer statistics.
 */
class DashboardController extends Controller
{
    /**
     * Display the admin dashboard overview page.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        
        if (!$user->hasPermission('dashboard.admin.view')) {
            abort(403, 'Akses ditolak.');
        }

        // Fetch the total amount of current quantity of each consumable type
        $consumableTypeStats = DB::table('lots')
            ->join('models', 'lots.model_id', '=', 'models.id')
            ->join('types', 'models.type_id', '=', 'types.id')
            ->where('types.is_consumable', true)
            ->groupBy('types.id', 'types.name')
            ->select(
                'types.name as type_name',
                'types.name as subcategory_name',
                DB::raw('SUM(lots.current_quantity) as total_quantity')
            )
            ->get();

        // Optimized Single Query: Fetch total number of assets for non_consumable categories under CFS and ICT organizers
        $nonConsumableCategoryStats = DB::table('assets')
            ->join('lots', 'assets.lot_id', '=', 'lots.id')
            ->join('organizers', 'lots.organizer_id', '=', 'organizers.id')
            ->join('models', 'lots.model_id', '=', 'models.id')
            ->join('types', 'models.type_id', '=', 'types.id')
            ->join('categories', 'types.category_id', '=', 'categories.id')
            ->where('types.is_consumable', false)
            ->whereIn('organizers.name', ['CFS', 'ICT'])
            ->groupBy('organizers.name', 'categories.id', 'categories.name')
            ->select(
                'organizers.name as organizer_name',
                'categories.name as category_name',
                DB::raw('COUNT(assets.id) as total_units')
            )
            ->get();

        $cfsCategoryStats = $nonConsumableCategoryStats
            ->where('organizer_name', 'CFS')
            ->map(fn ($item) => [
                'category_name' => $item->category_name,
                'total_units' => (int) $item->total_units,
                'total_assets' => (int) $item->total_units,
            ])
            ->values();

        $ictCategoryStats = $nonConsumableCategoryStats
            ->where('organizer_name', 'ICT')
            ->map(fn ($item) => [
                'category_name' => $item->category_name,
                'total_units' => (int) $item->total_units,
                'total_assets' => (int) $item->total_units,
            ])
            ->values();
        
        return Inertia::render('Smart/MultiRoles/Dashboard', [
            'user' => $user,
            'consumableTypeStats' => $consumableTypeStats,
            'consumableSubcategoryStats' => $consumableTypeStats,
            'cfsCategoryStats' => $cfsCategoryStats,
            'ictCategoryStats' => $ictCategoryStats,
        ]);
    }
}
