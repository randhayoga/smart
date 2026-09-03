<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestUnitAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Request History Controller managing user request browsing and detail inspection.
 */
class RequestHistoryController extends Controller
{
    /**
     * Menampilkan halaman riwayat permintaan dan peminjaman pengguna.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with([
            'approver',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.barang.uom',
            'items.subcategory.category',
            'items.subcategory.barangs.uom',
            'items.unitAssignments.unit',
            'project',
            'department',
            'approval.approver',
            'adminConfirmation.admin',
            'handover',
            'statusLogs.changer',
        ])
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($r) => $this->mapRequest($r));

        return Inertia::render('Smart/User/RequestHistory', [
            'user' => $request->user(),
            'requests' => $requests,
        ]);
    }

    /**
     * Menampilkan halaman detail dari permintaan tertentu.
     */
    public function show(Request $request, string $id): Response
    {
        $req = SmartRequest::with([
            'approver',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.barang.uom',
            'items.subcategory.category',
            'items.subcategory.barangs.uom',
            'items.unitAssignments.unit',
            'project',
            'department',
            'approval.approver',
            'adminConfirmation.admin',
            'handover',
            'statusLogs.changer',
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return Inertia::render('Smart/User/RequestHistoryDetail', [
            'user' => $request->user(),
            'requestId' => $req->id,
            'request' => $this->mapRequest($req),
            'placements' => [],
        ]);
    }

    /**
     * Memetakan data permintaan ke format array response yang diharapkan frontend.
     */
    private function mapRequest(SmartRequest $req): array
    {
        $statusMap = [
            'wait' => 'Menunggu approval',
            'approve' => 'Di-approve',
            'confirm' => 'Serah Terima',
            'handover' => 'Serah Terima',
            'borrow' => 'Dipinjam',
            'return' => 'Dipinjam',
            'success' => 'Selesai',
            'reject' => 'Ditolak',
            'cancel' => 'Dibatalkan',
            'pending' => 'Pending',
            'partial' => 'Partial',
        ];

        $type = $req->type_key;
        
        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            // Get assigned assets (serial numbers) via relation or query
            $assets = $item->relationLoaded('unitAssignments')
                ? $item->unitAssignments->pluck('unit.number')->filter()->values()->toArray()
                : RequestUnitAssignment::where('request_item_id', $item->id)
                    ->with('unit')
                    ->get()
                    ->pluck('unit.number')
                    ->filter()
                    ->values()
                    ->toArray();

            $subcatName = $item->barang?->subcategory?->name ?? $item->subcategory?->name ?? '-';
            $catName = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $isConsumable = (bool) ($item->barang?->subcategory?->category?->is_consumable ?? $item->subcategory?->category?->is_consumable ?? false);
            $imageUrl = $item->barang?->image_url
                ? '/media/' . $item->barang->image_url
                : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null);
            $uomName = $item->barang?->uom?->name 
                ?? $item->subcategory?->barangs?->first()?->uom?->name 
                ?? 'satuan';

            return [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'subcategory' => $subcatName,
                'brand' => $item->barang?->brand?->name ?? '-',
                'name' => $item->barang?->name ?? 'Tidak Spesifik',
                'spec' => $item->barang?->specification ?? '',
                'quantity' => $item->quantity_requested,
                'category' => $catName,
                'is_consumable' => $isConsumable,
                'imageUrl' => $imageUrl,
                'uom' => $uomName,
                'assets' => $assets,
                'status' => $item->status,
            ];
        });

        $logs = $req->statusLogs->map(function ($log) {
            $actorRole = 'User';
            $actorName = $log->changer->name ?? '-';
            if ($log->changer && $log->changer->role === 'admin') {
                $actorRole = 'Admin';
            } else if ($log->changer && in_array($log->changer->role, ['manager', 'ifs_manager'])) {
                $actorRole = 'Manager';
            }

            return [
                'id' => $log->id,
                'status_from' => $log->status_from,
                'status_to' => $log->status_to,
                'time' => $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-',
                'actor' => "{$actorRole}: {$actorName}",
                'user' => $actorName,
                'note' => $log->note ?? '',
            ];
        })->toArray();

        $pemanfaatanDetail = '-';
        if ($req->utilization === 'corporate') {
            $pemanfaatanDetail = $req->department?->org_name ?? $req->department?->name ?? '-';
        } else {
            if ($req->project) {
                $pemanfaatanDetail = $req->project->no_project 
                    ? "{$req->project->no_project} ({$req->project->project_name})" 
                    : ($req->project->project_name ?? '-');
            }
        }

        return [
            'id' => $req->id,
            'number' => $req->request_number,
            'type' => $type,
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $pemanfaatanDetail,
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $statusMap[$req->status] ?? $req->status,
            'raw_status' => $req->status,
            'created_at' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'items' => $items,
            'approver_name' => $req->approver?->name,
            'approval_by' => $req->approval?->approver?->name,
            'approval_at' => $req->approval?->decided_at?->format('d-m-Y H:i'),
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'confirmation_at' => $req->adminConfirmation?->decided_at?->format('d-m-Y H:i'),
            'return_confirmed_by' => $req->statusLogs->first(fn($log) => $log->status_from === 'return' && $log->status_to === 'success')?->changer?->name,
            'handover_method' => $req->handover ? ($req->handover->method === 'pickup' ? 'Ambil sendiri' : 'Diantar') : null,
            'handover_time' => $req->handover?->scheduled_date?->format('d-m-Y H:i'),
            'handover_location' => $req->handover?->location,
            'handover_note' => $req->handover?->note,
            'logs' => $logs,
        ];
    }
}
