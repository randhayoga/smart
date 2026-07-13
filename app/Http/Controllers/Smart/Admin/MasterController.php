<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterController extends Controller
{
    /**
     * Menampilkan halaman manajemen data master.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/Admin/MasterData', [
            'user'          => $request->user(),
            'categories'    => Category::orderBy('code')->get(),
            'subcategories' => Subcategory::with('category')->orderBy('code')->get(),
            'uoms'          => Uom::orderBy('name')->get(),
            'brands'        => Brand::orderBy('name')->get(),
            'organizers'    => Organizer::orderBy('name')->get(),
            'vendors'       => Vendor::orderBy('name')->get(),
            'locations'     => Location::orderBy('name')->get(),
            'floors'        => Floor::with('location')->orderBy('name')->get(),
            'rooms'         => Room::with('floor.location')->orderBy('name')->get(),
        ]);
    }
}
