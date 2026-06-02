<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
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
        $categories = \App\Models\Master\Category::orderBy('code')->get();
        $subcategories = \App\Models\Master\Subcategory::with('category')->orderBy('code')->get();
        $brands = \App\Models\Master\Brand::orderBy('name')->get();
        $uoms = \App\Models\Master\Uom::orderBy('name')->get();
        
        $barangs = \App\Models\Inventory\Barang::with(['subcategory.category', 'brand', 'uom'])
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

        return Inertia::render('Smart/Admin/ManajemenStok/ManajemenStok', [
            'user' => $request->user(),
            'categories' => $categories,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'uoms' => $uoms,
            'barangs' => $barangs,
        ]);
    }

    /**
     * Menampilkan halaman detail item inventaris.
     */
    public function show(Request $request, string $id): Response
    {
        $barang = \App\Models\Inventory\Barang::with(['subcategory.category', 'brand', 'uom'])
            ->where('number', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

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

        $lots = \App\Models\Inventory\Lot::with(['organizer', 'vendor', 'location', 'floor', 'room'])
            ->withCount('units')
            ->where('barang_id', $barang->id)
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'lotCode' => $lot->number,
                    'poNumber' => $lot->po_number,
                    'entryDate' => $lot->date_of_receipt ? $lot->date_of_receipt->format('d/m/Y') : '-',
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
                    'initial_quantity' => $lot->initial_quantity,
                    'current_quantity' => $lot->current_quantity,
                    'updated_at' => $lot->updated_at ? $lot->updated_at->format('d/m/Y H:i') : '-',
                ];
            });

        $brands = \App\Models\Master\Brand::orderBy('name')->get();
        $uoms = \App\Models\Master\Uom::orderBy('name')->get();
        $organizers = \App\Models\Master\Organizer::orderBy('name')->get();
        $vendors = \App\Models\Master\Vendor::orderBy('name')->get();
        $locations = \App\Models\Master\Location::orderBy('name')->get();
        $floors = \App\Models\Master\Floor::with('location')->orderBy('name')->get();
        $rooms = \App\Models\Master\Room::with('floor.location')->orderBy('name')->get();

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
        ]);
    }
}
