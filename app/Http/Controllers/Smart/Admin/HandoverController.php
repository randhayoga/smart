<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Request\Request as SmartRequest;

class HandoverController extends Controller
{
    /**
     * Menampilkan halaman daftar jadwal serah terima (Handover).
     */
    public function index()
    {
        $handovers = SmartRequest::with(['user', 'handover'])
            ->whereIn('status', ['confirm', 'handover'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $ho = $req->handover;
                $methodStr = 'Belum diatur';
                $timeStr = '-';
                $locStr = '-';

                if ($ho) {
                    $methodStr = $ho->method === 'pickup' ? 'Diambil sendiri' : 'Diantar';
                    $timeStr = $ho->scheduled_date ? $ho->scheduled_date->format('d-m-Y H:i') : '-';
                    $locStr = $ho->location ?? '-';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'requester' => $req->user->name ?? '-',
                    'method' => $methodStr,
                    'time' => $timeStr,
                    'location' => $locStr,
                ];
            });

        return Inertia::render('Smart/Admin/SerahTerima', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'handovers' => $handovers,
        ]);
    }

    /**
     * Menampilkan detail informasi jadwal serah terima (Handover).
     */
    public function show($id)
    {
        $req = SmartRequest::with(['user', 'handover', 'approver', 'approval.approver', 'adminConfirmation.admin', 'project', 'department', 'items.barang.subcategory.category', 'items.barang.brand', 'statusLogs.changer'])
            ->findOrFail($id);

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

        $ho = $req->handover;
        $handoverData = [
            'id' => $req->id,
            'number' => $req->request_number,
            'requester' => $req->user->name ?? '-',
            'method' => $ho && $ho->method === 'pickup' ? 'Ambil sendiri' : ($ho ? 'Diantar' : 'Belum diatur'),
            'time' => $ho && $ho->scheduled_date ? $ho->scheduled_date->format('d-m-Y H:i') : '-',
            'location' => $ho ? $ho->location : '-',
            'note' => $ho ? $ho->note : '',
            'status' => $req->status,
            'type' => $req->start_date ? 'peminjaman' : 'permintaan',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'approver' => $req->approver?->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'approval_at' => $req->approval?->decided_at?->format('d-m-Y H:i'),
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'confirmation_at' => $req->adminConfirmation?->decided_at?->format('d-m-Y H:i'),
            'logs' => $logs,
        ];

        $items = $req->items->map(function ($item) {
            $assets = \App\Models\Request\RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->filter()
                ->values()
                ->toArray();

            $availableUnits = \App\Models\Inventory\Unit::with(['lot', 'location'])
                ->whereHas('lot', function ($query) use ($item) {
                    $query->where('barang_id', $item->barang_id);
                })
                ->get()
                ->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'assetCode' => $unit->number,
                        'lotCode' => $unit->lot->number,
                        'status' => $unit->status === 'tersedia' ? 'Tersedia' : ucfirst($unit->status),
                        'condition' => $unit->condition,
                        'location' => $unit->location->name ?? '-',
                    ];
                });

            $barangId = $item->barang_id;
            $hasAnyUnit = \App\Models\Inventory\Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))->exists();

            if ($hasAnyUnit) {
                $availableStock = \App\Models\Inventory\Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))
                    ->where('status', 'tersedia')
                    ->count();
            } else {
                $availableStock = \App\Models\Inventory\Lot::where('barang_id', $barangId)->sum('current_quantity');
            }

            return [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'brand' => ($item->barang->brand->name ?? '-') . ' ' . ($item->barang->specification ?? ''),
                'name' => $item->barang->name ?? '-',
                'category' => $item->barang->subcategory->category->name ?? '-',
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $item->barang->image_url ?? null,
                'availableUnits' => $availableUnits,
                'is_consumable' => (bool) ($item->barang->subcategory->category->is_consumable ?? false),
                'stock' => $availableStock,
                'status' => $item->status,
            ];
        });

        $placements = \App\Models\Request\RequestUnitAssignment::whereIn('request_item_id', $req->items->pluck('id'))
            ->with('unit')
            ->get()
            ->filter(fn($asn) => $asn->unit && $asn->placement)
            ->mapWithKeys(fn($asn) => [$asn->unit->number => $asn->placement])
            ->toArray();

        return Inertia::render('Smart/Admin/SerahTerimaDetail', [
            'handover' => $handoverData,
            'items' => $items,
            'placements' => $placements,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    /**
     * Menyimpan alokasi unit aset untuk detail permintaan.
     */
    public function allocate(Request $request, $id)
    {
        $validated = $request->validate([
            'request_item_id' => 'required|exists:request_items,id',
            'unit_numbers' => 'required|array',
        ]);

        $item = \App\Models\Request\RequestItem::findOrFail($validated['request_item_id']);

        // Find the units corresponding to the unit numbers
        $units = \App\Models\Inventory\Unit::whereIn('number', $validated['unit_numbers'])->get();

        // Clear existing assignments for this request item
        \App\Models\Request\RequestUnitAssignment::where('request_item_id', $item->id)->delete();

        // Create new assignments
        foreach ($units as $unit) {
            \App\Models\Request\RequestUnitAssignment::create([
                'request_item_id' => $item->id,
                'unit_id' => $unit->id,
                'quantity_fulfilled' => 1,
                'assigned_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Alokasi aset berhasil disimpan.');
    }
}
