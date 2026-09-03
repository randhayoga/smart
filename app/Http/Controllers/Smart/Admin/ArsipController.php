<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestUnitAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Archive Controller providing searchable history and full lifecycle logs of fulfilled, rejected, and cancelled requests.
 */
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
                $type = $req->type_name;
                
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
        $req = SmartRequest::with([
            'user',
            'approver',
            'approval.approver',
            'adminConfirmation.admin',
            'statusLogs.changer',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.subcategory.category',
            'items.subcategory.barangs',
            'project',
            'department'
        ])
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

            $subcatName = $item->barang?->subcategory?->name ?? $item->subcategory?->name ?? '-';
            $catName = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $isConsumable = (bool) ($item->barang?->subcategory?->category?->is_consumable ?? $item->subcategory?->category?->is_consumable ?? false);
            $imageUrl = $item->barang?->image_url
                ? '/media/' . $item->barang->image_url
                : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null);
            $brandSpec = trim(($item->barang?->brand?->name ?? '') . ' ' . ($item->barang?->specification ?? ''));

            return [
                'id' => $item->id,
                'brand' => $brandSpec ?: '-',
                'name' => $item->barang?->name ?? 'Tidak Spesifik',
                'category' => $catName,
                'subcategory' => $subcatName,
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $imageUrl,
                'is_consumable' => $isConsumable,
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
                ? ($req->department?->org_name ?? $req->department?->name ?? '-') 
                : ($req->project ? ($req->project->no_project ? "{$req->project->no_project} ({$req->project->project_name})" : ($req->project->project_name ?? '-')) : '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'type' => $req->type_key,
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
