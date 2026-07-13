<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumableLotController extends Controller
{
    /**
     * Menampilkan halaman daftar stok habis pakai (Daftar Stok (Habis Pakai)).
     */
    public function index(Request $request): Response
    {
        $lots = Lot::with([
            'barang.subcategory.category',
            'barang.brand',
            'barang.uom',
            'organizer',
            'vendor',
            'location',
            'floor',
            'room',
            'project',
        ])
        ->whereHas('barang.subcategory.category', function ($query) {
            $query->where('is_consumable', true);
        })
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
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d/m/Y H:i') : '-',
                
                // Parent barang info
                'barang_code' => $lot->barang->number ?? '-',
                'barang_brand' => $lot->barang->brand->name ?? '-',
                'barang_nama' => $lot->barang->name ?? '-',
                'barang_specification' => $lot->barang->specification ?? '-',
                'barang_category' => $lot->barang->subcategory->category->name ?? '-',
                'barang_subcategory' => $lot->barang->subcategory->name ?? '-',
                'barang_uom' => $lot->barang->uom->name ?? '-',
            ];
        });

        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $floors = Floor::with('location')->orderBy('name')->get();
        $rooms = Room::with('floor.location')->orderBy('name')->get();

        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DaftarStokHabisPakai', [
            'user' => $request->user(),
            'lots' => $lots,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'projects' => $projects,
        ]);
    }
}
