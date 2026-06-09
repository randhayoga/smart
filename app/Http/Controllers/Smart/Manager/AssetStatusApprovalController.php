<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetStatusApprovalController extends Controller
{
    private function getStatusLabel($status)
    {
        $statusMap = [
            'available' => 'Tersedia',
            'tersedia' => 'Tersedia',
            'borrowed' => 'Dipinjam',
            'dipinjam' => 'Dipinjam',
            'maintenance' => 'Perbaikan',
            'perbaikan' => 'Perbaikan',
            'reserved' => 'Dipesan',
            'inactive' => 'Tidak Aktif',
            'broken' => 'Rusak',
            'rusak' => 'Rusak',
            'lost' => 'Hilang',
            'hilang' => 'Hilang',
        ];
        return $statusMap[strtolower($status)] ?? $status;
    }

    private function mapApproval($approval)
    {
        $unit = $approval->unit;
        $lot = $unit->lot ?? null;
        $barang = $lot->barang ?? null;
        
        // Fetch lifecycles for audit trail
        $lifecycles = UnitLifecycle::with(['requester', 'approver'])
            ->where('unit_id', $approval->unit_id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($lc) {
                $duration = '-';
                if ($lc->start_date && $lc->end_date) {
                    $duration = $lc->start_date->diffInDays($lc->end_date);
                }
                
                // Formulate actor name
                $actorRole = 'User';
                $actorName = $lc->requester->name ?? '-';
                if ($lc->requester && $lc->requester->role === 'admin') {
                    $actorRole = 'Admin';
                } else if ($lc->requester && in_array($lc->requester->role, ['manager', 'ifs_manager'])) {
                    $actorRole = 'Manager';
                }
                
                return [
                    'waktu' => $lc->start_date ? $lc->start_date->format('d-m-Y H:i') : '-',
                    'aksi_status' => $this->getStatusLabel($lc->status),
                    'aktor' => "{$actorRole}: {$actorName}",
                    'durasi' => $duration,
                    'catatan' => $lc->note ?? '-',
                ];
            });

        return [
            'id' => $approval->id,
            'unit_id' => $approval->unit_id,
            'asset_code' => $unit->number ?? '-',
            'category' => $barang->subcategory->category->name ?? '-',
            'subcategory' => $barang->subcategory->name ?? '-',
            'brand' => $barang->brand->name ?? '-',
            'specification' => $barang->specification ?? '-',
            'proposed_status' => $approval->proposed_status,
            'status_label' => $this->getStatusLabel($approval->proposed_status),
            'decision' => $approval->decision,
            'note' => $approval->note,
            'requested_by' => $approval->requester->name ?? '-',
            'requested_at' => $approval->requested_at ? $approval->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $approval->decided_at ? $approval->decided_at->format('d-m-Y H:i') : null,
            'approver_name' => $approval->approver->name ?? null,
            'memo_path' => $approval->memo_path,
            'memo_name' => $approval->memo_name,
            'unit_details' => [
                'id' => $unit->id,
                'number' => $unit->number ?? '-',
                'status' => $this->getStatusLabel($unit->status),
                'condition' => $unit->condition ?? '-',
                'price' => $unit->price ? number_format($unit->price, 0, ',', '.') : '-',
                'image_url' => $unit->image_url ? '/storage/' . $unit->image_url : ($lot->image_url ? '/storage/' . $lot->image_url : null),
                'vehicle_registration' => $unit->vehicle_registration ?? '-',
                'location' => $unit->location->name ?? '-',
                'floor' => $unit->floor->name ?? null,
                'room' => $unit->room->name ?? null,
                
                // Lot info
                'lot_code' => $lot->number ?? '-',
                'organizer' => $lot->organizer->name ?? '-',
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('d-m-Y') : '-',
                'vendor' => $lot->vendor->name ?? '-',
                'po_number' => $lot->po_number ?? '-',
                'barang_code' => $barang->number ?? '-',
                'barang_spec' => $barang->specification ?? '-',
                'barang_unit' => $barang->uom->name ?? 'pcs',
                
                // History logs
                'lifecycles' => $lifecycles,
            ]
        ];
    }

    public function index(Request $request): Response
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Manager.');
        }

        $approvals = UnitStatusApproval::with([
            'unit.lot.barang.subcategory.category',
            'unit.lot.barang.brand',
            'unit.lot.barang.uom',
            'unit.lot.organizer',
            'unit.lot.vendor',
            'unit.location',
            'unit.floor',
            'unit.room',
            'requester'
        ])
        ->where('decision', 'pending')
        ->orderBy('id', 'desc')
        ->get()
        ->map(fn($app) => $this->mapApproval($app));

        return Inertia::render('Smart/Manager/PerluApproveStatus', [
            'user' => $request->user(),
            'approvals' => $approvals,
        ]);
    }

    public function approvedList(Request $request): Response
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Manager.');
        }

        $approvals = UnitStatusApproval::with([
            'unit.lot.barang.subcategory.category',
            'unit.lot.barang.brand',
            'unit.lot.barang.uom',
            'unit.lot.organizer',
            'unit.lot.vendor',
            'unit.location',
            'unit.floor',
            'unit.room',
            'requester',
            'approver'
        ])
        ->where('decision', '!=', 'pending')
        ->orderBy('id', 'desc')
        ->get()
        ->map(fn($app) => $this->mapApproval($app));

        return Inertia::render('Smart/Manager/SudahApproveStatus', [
            'user' => $request->user(),
            'approvals' => $approvals,
        ]);
    }

    public function action(Request $request)
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:unit_status_approvals,id',
            'decision' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['decision'];
        $note = $validated['note'];

        DB::transaction(function () use ($ids, $decision, $note, $request) {
            foreach ($ids as $id) {
                $approval = UnitStatusApproval::findOrFail($id);
                
                // If it is already processed, skip
                if ($approval->decision !== 'pending') {
                    continue;
                }

                $approval->update([
                    'decision' => $decision,
                    'note' => $note ?? ($decision === 'approved' ? 'Disetujui oleh Manager' : 'Ditolak oleh Manager'),
                    'approver_id' => $request->user()->id,
                    'decided_at' => now(),
                ]);

                if ($decision === 'approved') {
                    $unit = Unit::findOrFail($approval->unit_id);
                    $oldStatus = $unit->status;
                    $newStatus = $approval->proposed_status;

                    // Update unit status
                    $unit->update(['status' => $newStatus]);

                    // Close any active unit lifecycles (end_date = now())
                    UnitLifecycle::where('unit_id', $unit->id)
                        ->whereNull('end_date')
                        ->update(['end_date' => now()]);

                    // Create new lifecycle log
                    UnitLifecycle::create([
                        'unit_id' => $unit->id,
                        'status' => $newStatus,
                        'start_date' => now(),
                        'end_date' => null,
                        'requester_id' => $approval->requester_id,
                        'approver_id' => $request->user()->id,
                        'note' => $note ?? "Pembaruan status dari {$this->getStatusLabel($oldStatus)} menjadi {$this->getStatusLabel($newStatus)} disetujui oleh Manager.",
                    ]);
                }
            }
        });

        $message = $decision === 'approved' 
            ? 'Status perubahan aset berhasil disetujui.' 
            : 'Status perubahan aset berhasil ditolak.';

        return redirect()->back()->with('success', $message);
    }
}
