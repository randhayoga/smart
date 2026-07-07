<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DaftarAsetController extends Controller
{
    /**
     * Menampilkan halaman daftar aset (Daftar Aset).
     */
    public function __invoke(Request $request): Response
    {
        $units = \App\Models\Inventory\Unit::with([
            'location', 'floor', 'room', 'statusApprovals',
            'lot.barang.subcategory.category', 'lot.barang.brand',
            'lot.organizer', 'lot.vendor'
        ])
        ->get()
        ->map(function ($unit) {
            $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
            $barang = $unit->lot->barang ?? null;
            return [
                'id' => $unit->id,
                'number' => $unit->number, // Kode Aset
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

        $locations = \App\Models\Master\Location::orderBy('name')->get();
        $floors = \App\Models\Master\Floor::with('location')->orderBy('name')->get();
        $rooms = \App\Models\Master\Room::with('floor.location')->orderBy('name')->get();
        $organizers = \App\Models\Master\Organizer::orderBy('name')->get();
        $vendors = \App\Models\Master\Vendor::orderBy('name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DaftarAset', [
            'user' => $request->user(),
            'units' => $units,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'organizers' => $organizers,
            'vendors' => $vendors,
        ]);
    }
}
