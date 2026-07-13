<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestUnitAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArsipController extends Controller
{
    /**
     * Menampilkan halaman daftar arsip permintaan/peminjaman barang (Arsip).
     */
    public function index()
    {
        $archiveList = SmartRequest::with(['user', 'items'])
            ->whereIn('status', ['success', 'reject', 'cancel'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $type = $req->start_date ? 'Peminjaman' : 'Permintaan';
                
                $statusStr = 'Sukses';
                if ($req->status === 'reject') {
                    $statusStr = 'Ditolak';
                } elseif ($req->status === 'cancel') {
                    $statusStr = 'Dibatalkan';
                }

                $timeStr = $req->start_date ? $req->start_date->format('d-m-Y H:i') : ($req->created_at ? $req->created_at->format('d-m-Y H:i') : '-');
                $endTimeStr = $req->end_date ? $req->end_date->format('d-m-Y H:i') : '-';

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'type' => $type,
                    'status' => $statusStr,
                    'requester' => $req->user->name ?? '-',
                    'startTime' => $timeStr,
                    'endTime' => $endTimeStr,
                ];
            });

        return Inertia::render('Smart/Admin/Arsip', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'archiveList' => $archiveList,
        ]);
    }

    /**
     * Menampilkan detail informasi arsip permintaan/peminjaman barang.
     */
    public function show($id)
    {
        $req = SmartRequest::with(['user', 'approver', 'approval.approver', 'adminConfirmation.admin', 'statusLogs.changer', 'items.barang.subcategory.category', 'items.barang.brand', 'project', 'department'])
            ->findOrFail($id);

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            $assets = RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->filter()
                ->values()
                ->toArray();

            $barangId = $item->barang_id;
            $hasAnyUnit = Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))->exists();

            return [
                'id' => $item->id,
                'brand' => ($item->barang->brand->name ?? '-') . ' ' . ($item->barang->specification ?? ''),
                'name' => $item->barang->name ?? '-',
                'category' => $item->barang->subcategory->category->name ?? '-',
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $item->barang->image_url ?? null,
                'is_consumable' => (bool) ($item->barang->subcategory->category->is_consumable ?? false),
            ];
        });

        $returnConfirmation = $req->statusLogs
            ->where('status_from', 'return')
            ->where('status_to', 'success')
            ->first();

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

        $mappedRequest = [
            'id' => $req->id,
            'number' => $req->request_number,
            'requester' => $req->user->name ?? '-',
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'return_confirmed_by' => $returnConfirmation?->changer?->name,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'updatedAt' => $req->updated_at ? $req->updated_at->format('d-m-Y H:i') : '-',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'type' => $req->start_date ? 'peminjaman' : 'permintaan',
            'items' => $items,
            'logs' => $logs,
        ];

        $placements = RequestUnitAssignment::whereIn('request_item_id', $req->items->pluck('id'))
            ->with('unit')
            ->get()
            ->filter(fn($asn) => $asn->unit && $asn->placement)
            ->mapWithKeys(fn($asn) => [$asn->unit->number => $asn->placement])
            ->toArray();

        return Inertia::render('Smart/Admin/ArsipDetail', [
            'requestId' => $req->id,
            'request' => $mappedRequest,
            'placements' => $placements,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }
}
