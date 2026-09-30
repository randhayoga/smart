<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrdOrgchart;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master Data Controller rendering centralized master data configuration (categories, brands, locations, vendors).
 */
class MasterController extends Controller
{
    /**
     * Display the centralized master data management page.
     */
    public function index(Request $request): Response
    {
        $departments = HrdOrgchart::select('id', 'org_name', 'org_code')
            ->whereNotNull('org_name')
            ->orderBy('org_name')
            ->get()
            ->map(fn($d) => [
                'id' => (int) $d->id,
                'name' => $d->org_code ? "{$d->org_code} - {$d->org_name}" : $d->org_name,
                'org_name' => $d->org_name,
                'org_code' => $d->org_code,
            ]);

        return Inertia::render('Smart/Admin/MasterData/MasterData', [
            'user'          => $request->user(),
            'categories'    => Category::orderBy('code')->get(),
            'subcategories' => Subcategory::with('category')->orderBy('code')->get(),
            'uoms'          => Uom::orderBy('name')->get(),
            'brands'        => Brand::orderBy('name')->get(),
            'organizers'    => Organizer::orderBy('name')->get(),
            'vendors'       => Vendor::orderBy('name')->get(),
            'locations'     => Location::with(['parent', 'relatedDepartment'])->withCount('children')->orderBy('name')->get(),
            'departments'   => $departments,
        ]);
    }
}
