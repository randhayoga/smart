<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendingNonaktifController extends Controller
{
    /**
     * Display a listing of pending nonaktif units (Pending:BoD/BoC and Pending:DM).
     */
    public function index(Request $request): Response
    {
        $units = Unit::with([
            'location', 'floor', 'room', 'statusApprovals',
            'lot.barang.subcategory.category', 'lot.barang.brand',
            'lot.organizer', 'lot.vendor', 'lifecycles.actor'
        ])
        ->where('status', 'like', 'Pending%')
        ->get()
        ->map(function ($unit) {
            $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
            $approvedApproval = $unit->status === 'Tidak Aktif' 
                ? $unit->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;
            $barang = $unit->lot->barang ?? null;
            return [
                'id' => $unit->id,
                'number' => $unit->number,
                'status' => $unit->status,
                'proposed_status' => $pendingApproval 
                    ? $pendingApproval->proposed_condition 
                    : ($approvedApproval ? $approvedApproval->proposed_condition : null),
                'proposed_condition' => $pendingApproval 
                    ? $pendingApproval->proposed_condition 
                    : ($approvedApproval ? $approvedApproval->proposed_condition : null),
                'memo_url' => $pendingApproval 
                    ? $pendingApproval->memo_url 
                    : ($approvedApproval ? $approvedApproval->memo_url : null),
                'lost_doc_url' => $pendingApproval 
                    ? $pendingApproval->lost_doc_url 
                    : ($approvedApproval ? $approvedApproval->lost_doc_url : null),
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

                // Parent barang info
                'barang_id' => $barang->id ?? null,
                'barang_code' => $barang->number ?? '-',
                'barang_nama' => $barang->name ?? '-',
                'barang_brand' => $barang->brand->name ?? '-',
                'barang_specification' => $barang->specification ?? '-',
                'barang_category' => $barang->subcategory->category->name ?? '-',
                'barang_subcategory' => $barang->subcategory->name ?? '-',
                'barang_uom' => $barang->uom->name ?? '-',

                // Audit trails (lifecycles)
                'lifecycles' => $unit->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => $log->actor->name ?? '-',
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ];
        });

        $locations = Location::orderBy('name')->get();
        $floors = Floor::with('location')->orderBy('name')->get();
        $rooms = Room::with('floor.location')->orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DaftarPendingNonaktif', [
            'user' => $request->user(),
            'units' => $units,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'projects' => $projects,
        ]);
    }
}
