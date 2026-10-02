<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pending Aktivasi Controller listing asset units pending manager activation approval.
 */
class PendingAktivasiController extends Controller
{
    /**
     * Display a listing of pending activation units.
     */
    public function index(Request $request): Response
    {
        $units = Unit::with([
            'location.parent', 'activationApprovals',
            'lot.barang.subcategory.category', 'lot.barang.brand',
            'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'lifecycles.actor'
        ])
        ->where(function ($q) {
            $q->where('status', 'Belum Diverifikasi')
              ->orWhereHas('activationApprovals', fn($aq) => $aq->where('decision', 'pending'));
        })
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($unit) {
            $barang = $unit->lot->barang ?? null;
            return [
                'id' => $unit->id,
                'number' => $unit->number,
                'legacy_number' => $unit->legacy_number,
                'status' => $unit->status,
                'condition' => $unit->condition,
                'type' => $unit->type,
                'classification' => $unit->classification,
                'price' => $unit->price,
                'image_url' => $unit->image_url,
                'vehicle_registration' => $unit->vehicle_registration,
                'created_at' => $unit->created_at?->toIso8601String(),
                'updated_at' => $unit->updated_at ? $unit->updated_at->format('d-m-Y H:i') : '-',
                
                // Location info
                'location' => $unit->location ? $unit->location->full_name : '-',
                'location_id' => $unit->location_id,

                // Parent lot info
                'lot_id' => $unit->lot_id,
                'lot_number' => $unit->lot->number ?? '-',
                'lot_imageUrl' => $unit->lot->image_url ?? null,
                'lot_unitPrice' => $unit->lot->unit_price ?? null,
                'organizer' => $unit->lot->organizer->name ?? '-',
                'organizer_id' => $unit->lot->organizer_id ?? null,
                'vendor' => $unit->lot?->vendor_name ?? '-',
                'vendor_id' => $unit->lot->vendor_id ?? null,
                'legacy_vendor_id' => $unit->lot->legacy_vendor_id ?? null,
                'lot_organizer' => $unit->lot->organizer->name ?? '-',
                'lot_vendor' => $unit->lot?->vendor_name ?? '-',
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
                        'aktor' => $log->actor->name ?? 'System',
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ];
        });

        $locations = Location::with('parent')->active()->orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DaftarPendingAktivasi', [
            'user' => $request->user(),
            'units' => $units,
            'locations' => $locations,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'projects' => $projects,
        ]);
    }
}
