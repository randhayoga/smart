<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestFulfillment;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Handover Controller managing pickup/delivery schedules and asset serial assignments for confirmed requests.
 */
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
        $req = SmartRequest::with([
            'user',
            'handover',
            'approver',
            'approval.approver',
            'adminConfirmation.admin',
            'project',
            'department',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.subcategory.category',
            'items.subcategory.barangs',
            'statusLogs.changer'
        ])
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
            'type' => $req->type_key,
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department?->org_name ?? $req->department?->name ?? '-') 
                : ($req->project ? ($req->project->no_project ? "{$req->project->no_project} ({$req->project->project_name})" : ($req->project->project_name ?? '-')) : '-'),
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
            $assets = RequestFulfillment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->filter()
                ->values()
                ->toArray();

            $availableUnits = Unit::with(['lot', 'location'])
                ->whereHas('lot', function ($query) use ($item) {
                    if ($item->barang_id) {
                        $query->where('barang_id', $item->barang_id);
                    } else {
                        $query->whereHas('barang', fn($bq) => $bq->where('subcategory_id', $item->subcategory_id));
                    }
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
            if ($barangId) {
                $hasAnyUnit = Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))->exists();
                if ($hasAnyUnit) {
                    $availableStock = Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))
                        ->where('status', 'tersedia')
                        ->count();
                } else {
                    $availableStock = Lot::where('barang_id', $barangId)->sum('current_quantity');
                }
            } else {
                $hasAnyUnit = Unit::whereHas('lot.barang', fn($q) => $q->where('subcategory_id', $item->subcategory_id))->exists();
                if ($hasAnyUnit) {
                    $availableStock = Unit::whereHas('lot.barang', fn($q) => $q->where('subcategory_id', $item->subcategory_id))
                        ->where('status', 'tersedia')
                        ->count();
                } else {
                    $availableStock = Lot::whereHas('barang', fn($q) => $q->where('subcategory_id', $item->subcategory_id))->sum('current_quantity');
                }
            }

            $subcatName = $item->barang?->subcategory?->name ?? $item->subcategory?->name ?? '-';
            $catName = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $isConsumable = (bool) ($item->barang?->subcategory?->category?->is_consumable ?? $item->subcategory?->category?->is_consumable ?? false);
            $imageUrl = $item->barang?->image_url
                ? '/media/' . $item->barang->image_url
                : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null);
            $brandSpec = trim(($item->barang?->brand?->name ?? '') . ' ' . ($item->barang?->specification ?? ''));

            return [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'brand' => $brandSpec ?: '-',
                'name' => $item->barang?->name ?? 'Tidak Spesifik',
                'category' => $catName,
                'subcategory' => $subcatName,
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $imageUrl,
                'availableUnits' => $availableUnits,
                'is_consumable' => $isConsumable,
                'stock' => $availableStock,
                'status' => $item->status,
            ];
        });

        $placements = RequestFulfillment::whereIn('request_item_id', $req->items->pluck('id'))
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

        $item = RequestItem::findOrFail($validated['request_item_id']);

        // Find the units corresponding to the unit numbers
        $units = Unit::whereIn('number', $validated['unit_numbers'])->get();

        // Clear existing assignments for this request item
        RequestFulfillment::where('request_item_id', $item->id)->delete();

        // Create new assignments
        foreach ($units as $unit) {
            RequestFulfillment::create([
                'request_item_id' => $item->id,
                'unit_id' => $unit->id,
                'quantity_fulfilled' => 1,
                'assigned_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Alokasi aset berhasil disimpan.');
    }
}
