<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Room;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitScanController extends Controller
{
    /**
     * Show the standalone mobile scan page for the specified asset (unit).
     */
    public function show(Unit $unit): Response
    {
        $unit->load([
            'location', 'floor', 'room', 'statusApprovals',
            'lot.barang.subcategory.category', 'lot.barang.brand',
            'lot.organizer', 'lot.vendor'
        ]);

        $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
        $barang = $unit->lot->barang ?? null;

        $mappedUnit = [
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
            'lot_organizer' => $unit->lot->organizer->name ?? '-',
            'lot_vendor' => $unit->lot->vendor->name ?? '-',
            'lot_po_number' => $unit->lot->po_number ?? '-',
            'lot_date_of_receipt' => ($unit->lot && $unit->lot->date_of_receipt) ? $unit->lot->date_of_receipt->format('Y-m-d') : null,
            'lot_age' => $unit->lot ? $unit->lot->age : null,

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

        $locations = Location::orderBy('name')->get();
        $floors = Floor::with('location')->orderBy('name')->get();
        $rooms = Room::with('floor.location')->orderBy('name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/ScanAsset', [
            'asset' => $mappedUnit,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'lot' => $unit->lot,
            'barang' => $barang,
        ]);
    }
}
