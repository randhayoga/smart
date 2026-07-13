<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManajemenStokController extends Controller
{
    /**
     * Menampilkan halaman manajemen stok barang (Inventory).
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('code')->get();
        $subcategories = Subcategory::with('category')->orderBy('code')->get();
        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        
        $barangs = Barang::with(['subcategory.category', 'brand', 'uom'])
            ->get()
            ->map(function ($barang) {
                $isConsumable = (bool)($barang->subcategory->category->is_consumable ?? false);
                $amount = $isConsumable 
                    ? (int)$barang->lots()->sum('current_quantity')
                    : (int)$barang->lots()->withCount('units')->get()->sum('units_count');
                
                return [
                    'id' => $barang->id,
                    'code' => $barang->number,
                    'category' => $barang->subcategory->category->name ?? '-',
                    'subcategory' => $barang->subcategory->name ?? '-',
                    'brand' => $barang->brand->name ?? '-',
                    'name' => $barang->name,
                    'specification' => $barang->specification,
                    'lastUpdate' => $barang->updated_at ? $barang->updated_at->format('d/m/Y H:i') : '-',
                    'amount' => $amount,
                    'image_url' => $barang->image_url,
                    'uom' => $barang->uom->name ?? '-',
                    'subcategory_id' => $barang->subcategory_id,
                    'category_id' => $barang->subcategory->category_id ?? null,
                    'is_consumable' => (bool)($barang->subcategory->category->is_consumable ?? false),
                    'brand_id' => $barang->brand_id,
                    'uom_id' => $barang->uom_id,
                ];
            });

        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/ManajemenStok', [
            'user' => $request->user(),
            'categories' => $categories,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'uoms' => $uoms,
            'barangs' => $barangs,
            'projects' => $projects,
        ]);
    }

    /**
     * Menampilkan halaman detail item inventaris.
     */
    public function show(Request $request, string $id): Response
    {
        $query = Barang::with(['subcategory.category', 'brand', 'uom']);
        if (is_numeric($id)) {
            $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('number', $id);
            });
        } else {
            $query->where('number', $id);
        }
        $barang = $query->firstOrFail();

        $isConsumable = (bool)($barang->subcategory->category->is_consumable ?? false);
        $amount = $isConsumable 
            ? (int)$barang->lots()->sum('current_quantity')
            : (int)$barang->lots()->withCount('units')->get()->sum('units_count');

        $formattedBarang = [
            'id' => $barang->id,
            'code' => $barang->number,
            'category' => $barang->subcategory->category->name ?? '-',
            'subcategory' => $barang->subcategory->name ?? '-',
            'brand' => $barang->brand->name ?? '-',
            'name' => $barang->name,
            'specification' => $barang->specification,
            'lastUpdate' => $barang->updated_at ? $barang->updated_at->format('d/m/Y H:i') : '-',
            'amount' => $amount,
            'image_url' => $barang->image_url,
            'uom' => $barang->uom->name ?? '-',
            'subcategory_id' => $barang->subcategory_id,
            'category_id' => $barang->subcategory->category_id ?? null,
            'is_consumable' => (bool)($barang->subcategory->category->is_consumable ?? false),
            'brand_id' => $barang->brand_id,
            'uom_id' => $barang->uom_id,
        ];

        $lots = Lot::with(['organizer', 'vendor', 'location', 'floor', 'room', 'project'])
            ->withCount(['units', 'units as available_units_count' => function ($query) {
                $query->where('status', 'Tersedia');
            }])
            ->where('barang_id', $barang->id)
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'number' => $lot->number,
                    'barang_id' => $lot->barang_id,
                    'po_number' => $lot->po_number,
                    'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('Y-m-d') : null,
                    'organizer' => $lot->organizer->name ?? '-',
                    'organizer_id' => $lot->organizer_id,
                    'vendor' => $lot->vendor->name ?? '-',
                    'vendor_id' => $lot->vendor_id,
                    'location' => $lot->location->name ?? '-',
                    'location_id' => $lot->location_id,
                    'floor' => $lot->floor->name ?? null,
                    'floor_id' => $lot->floor_id,
                    'room' => $lot->room->name ?? null,
                    'room_id' => $lot->room_id,
                    'unitPrice' => $lot->unit_price,
                    'imageUrl' => $lot->image_url,
                    'assetCount' => $lot->units_count,
                    'availableAssetCount' => $lot->available_units_count,
                    'initial_quantity' => $lot->initial_quantity,
                    'current_quantity' => $lot->current_quantity,
                    'age' => $lot->age,
                    'burden' => $lot->burden,
                    'project_id' => $lot->project_id,
                    'project_name' => $lot->project ? $lot->project->project_name : null,
                    'project_no' => $lot->project ? $lot->project->no_project : null,
                    'updated_at' => $lot->updated_at ? $lot->updated_at->format('d/m/Y H:i') : '-',
                ];
            });

        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $floors = Floor::with('location')->orderBy('name')->get();
        $rooms = Room::with('floor.location')->orderBy('name')->get();

        $units = [];
        if (!$isConsumable) {
            $units = Unit::with([
                'location', 'floor', 'room', 'statusApprovals',
                'lot.barang.subcategory.category', 'lot.barang.brand', 'lot.barang.uom',
                'lot.organizer', 'lot.vendor'
            ])
            ->whereHas('lot', function ($query) use ($barang) {
                $query->where('barang_id', $barang->id);
            })
            ->get()
            ->map(function ($unit) {
                $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
                $barang = $unit->lot->barang ?? null;
                return [
                    'id' => $unit->id,
                    'number' => $unit->number,
                    'status' => $unit->status,
                    'proposed_status' => $pendingApproval ? $pendingApproval->proposed_status : null,
                    'doc_url' => $pendingApproval ? $pendingApproval->doc_url : null,
                    'condition' => $unit->condition,
                    'price' => $unit->price,
                    'image_url' => $unit->image_url,
                    'vehicle_registration' => $unit->vehicle_registration,
                    'updated_at' => $unit->updated_at ? $unit->updated_at->format('d/m/Y H:i') : '-',
                    
                    // Location info
                    'location' => $unit->location->name ?? '-',
                    'location_id' => $unit->location_id,
                    'floor' => $unit->floor->name ?? null,
                    'floor_id' => $unit->floor_id,
                    'room' => $unit->room->name ?? null,
                    'room_id' => $unit->room_id,

                    // Parent lot info
                    'lot_id' => $unit->lot_id,
                    'lot_number' => $unit->lot->number ?? '-',
                    'lot_imageUrl' => $unit->lot->image_url ?? null,
                    'lot_unitPrice' => $unit->lot->unit_price ?? null,
                    'organizer' => $unit->lot->organizer->name ?? '-',
                    'organizer_id' => $unit->lot->organizer_id ?? null,
                    'vendor' => $unit->lot->vendor->name ?? '-',
                    'vendor_id' => $unit->lot->vendor_id ?? null,
                    'lot_organizer' => $unit->lot->organizer->name ?? '-',
                    'lot_vendor' => $unit->lot->vendor->name ?? '-',
                    'lot_po_number' => $unit->lot->po_number ?? '-',
                    'lot_date_of_receipt' => ($unit->lot && $unit->lot->date_of_receipt) ? $unit->lot->date_of_receipt->format('Y-m-d') : null,
                    'lot_age' => $unit->lot->age ?? null,

                    // Parent barang info
                    'barang_id' => $barang->id ?? null,
                    'barang_code' => $barang->number ?? '-',
                    'barang_nama' => $barang->name ?? '-',
                    'barang_brand' => $barang->brand->name ?? '-',
                    'barang_specification' => $barang->specification ?? '-',
                    'barang_category' => $barang->subcategory->category->name ?? '-',
                    'barang_subcategory' => $barang->subcategory->name ?? '-',
                    'barang_uom' => $barang->uom->name ?? '-',
                ];
            });
        }

        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DetailBarang', [
            'user' => $request->user(),
            'itemId' => $id,
            'barang' => $formattedBarang,
            'brands' => $brands,
            'uoms' => $uoms,
            'lots' => $lots,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'units' => $units,
            'projects' => $projects,
        ]);
    }
}
