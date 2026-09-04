<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Borrowed Controller tracking active asset loans, due dates, borrow durations, and current placements.
 */
class BorrowedController extends Controller
{
    /**
     * Menampilkan halaman daftar peminjaman aktif (Lacak Peminjaman).
     */
    public function index()
    {
        $borrowedList = SmartRequest::with(['user', 'handover', 'items'])
            ->where('status', 'borrow')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                // Calculate days left
                $daysLeft = '-';
                $dueDateStr = '-';
                if ($req->end_date) {
                    $dueDateStr = $req->end_date->format('d-m-Y H:i');
                    $diff = now()->diffInDays($req->end_date, false);
                    $daysLeft = $diff >= 0 ? (string)(int)$diff : 'Telat ' . abs((int)$diff) . ' hari';
                }

                // Calculate days passed since the goods were taken
                $daysPassed = '-';
                $confirmedAt = $req->handover?->user_confirmed_at ?? $req->updated_at;
                if ($confirmedAt) {
                    $diffPassed = $confirmedAt->diffInDays(now(), false);
                    $daysPassedVal = max(0, (int) $diffPassed);
                    $daysPassed = (string)$daysPassedVal . ' hari';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'borrower' => $req->user->name ?? '-',
                    'dueDate' => $dueDateStr,
                    'daysLeft' => $daysLeft,
                    'daysPassed' => $daysPassed,
                ];
            });

        return Inertia::render('Smart/Admin/Borrowed', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'borrowedList' => $borrowedList,
        ]);
    }

    /**
     * Menampilkan detail informasi peminjaman barang aktif.
     */
    public function show($id)
    {
        $req = SmartRequest::with([
            'user',
            'approver',
            'approval.approver',
            'adminConfirmation.admin',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.subcategory.category',
            'items.subcategory.barangs',
            'project',
            'department',
            'statusLogs.changer'
        ])
            ->findOrFail($id);

        $daysLeft = '-';
        $dueDateStr = '-';
        if ($req->end_date) {
            $dueDateStr = $req->end_date->format('d-m-Y H:i');
            $diff = now()->diffInDays($req->end_date, false);
            $daysLeft = $diff >= 0 ? (string)$diff : 'Telat ' . abs($diff) . ' hari';
        }

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

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

        $items = $req->items->map(function ($item) {
            $assets = RequestFulfillment::where('request_item_id', $item->id)
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

        $borrowedData = [
            'id' => $req->id,
            'number' => $req->request_number,
            'requester' => $req->user->name ?? '-',
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
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
            'dueDate' => $dueDateStr,
            'daysLeft' => $daysLeft,
            'logs' => $logs,
        ];

        $placements = RequestFulfillment::whereIn('request_item_id', $req->items->pluck('id'))
            ->with('unit')
            ->get()
            ->filter(fn($asn) => $asn->unit && $asn->placement)
            ->mapWithKeys(fn($asn) => [$asn->unit->number => $asn->placement])
            ->toArray();

        return Inertia::render('Smart/Admin/BorrowedDetail', [
            'borrowedId' => $req->id,
            'request' => $borrowedData,
            'placements' => $placements,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }
}
