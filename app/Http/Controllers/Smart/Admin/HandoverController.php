<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
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
     * Display the handover schedule listing page (Handover).
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

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/ActiveRequests', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'activeTab' => 'Serah Terima',
            'handovers' => $handovers,
        ]);
    }

    /**
     * Display detailed handover schedule information for a specified request.
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
            'items.model.type.category',
            'items.model.brand',
            'items.type.category',
            'items.type.models',
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
                ->with('asset')
                ->get()
                ->pluck('asset.number')
                ->filter()
                ->values()
                ->toArray();

            $availableAssets = Asset::with(['lot', 'location'])
                ->whereHas('lot', function ($query) use ($item) {
                    if ($item->model_id) {
                        $query->where('model_id', $item->model_id);
                    } else {
                        $query->whereHas('model', fn($mq) => $mq->where('type_id', $item->type_id));
                    }
                })
                ->get()
                ->map(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'assetCode' => $asset->number,
                        'assetNumber' => $asset->number,
                        'lotCode' => $asset->lot->number,
                        'status' => $asset->status === 'tersedia' ? 'Tersedia' : ucfirst($asset->status),
                        'condition' => $asset->condition,
                        'location' => $asset->location->name ?? '-',
                    ];
                });

            $modelId = $item->model_id;
            if ($modelId) {
                $hasAnyAsset = Asset::whereHas('lot', fn($q) => $q->where('model_id', $modelId))->exists();
                if ($hasAnyAsset) {
                    $availableStock = Asset::whereHas('lot', fn($q) => $q->where('model_id', $modelId))
                        ->where('status', 'tersedia')
                        ->count();
                } else {
                    $availableStock = Lot::where('model_id', $modelId)->sum('current_quantity');
                }
            } else {
                $hasAnyAsset = Asset::whereHas('lot.model', fn($q) => $q->where('type_id', $item->type_id))->exists();
                if ($hasAnyAsset) {
                    $availableStock = Asset::whereHas('lot.model', fn($q) => $q->where('type_id', $item->type_id))
                        ->where('status', 'tersedia')
                        ->count();
                } else {
                    $availableStock = Lot::whereHas('model', fn($q) => $q->where('type_id', $item->type_id))->sum('current_quantity');
                }
            }

            $typeName = $item->model?->type?->name ?? $item->type?->name ?? '-';
            $catName = $item->model?->type?->category?->name ?? $item->type?->category?->name ?? '-';
            $isConsumable = (bool) $item->is_consumable;
            $imageUrl = $item->model?->image_url
                ? '/media/' . $item->model->image_url
                : (($firstModel = $item->type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null);
            $brandSpec = trim(($item->model?->brand?->name ?? '') . ' ' . ($item->model?->specification ?? ''));

            return [
                'id' => $item->id,
                'model_id' => $item->model_id,
                'barang_id' => $item->model_id,
                'brand' => $brandSpec ?: '-',
                'name' => $item->model?->name ?? 'Tidak Spesifik',
                'category' => $catName,
                'type' => $typeName,
                'subcategory' => $typeName,
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $imageUrl,
                'availableUnits' => $availableAssets,
                'availableAssets' => $availableAssets,
                'is_consumable' => $isConsumable,
                'stock' => $availableStock,
                'status' => $item->status,
            ];
        });

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/Details/HandoverDetail', [
            'handover' => $handoverData,
            'items' => $items,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    /**
     * Store asset allocations for the specified request item.
     */
    public function allocate(Request $request, $id)
    {
        $validated = $request->validate([
            'request_item_id' => 'required|exists:request_items,id',
            'unit_numbers' => 'nullable|array',
            'asset_numbers' => 'nullable|array',
        ]);

        $item = RequestItem::findOrFail($validated['request_item_id']);
        $numbers = $validated['asset_numbers'] ?? $validated['unit_numbers'] ?? [];

        // Find the assets corresponding to the numbers
        $assets = Asset::whereIn('number', $numbers)->get();

        // Clear existing assignments for this request item
        RequestFulfillment::where('request_item_id', $item->id)->delete();

        // Create new assignments
        foreach ($assets as $asset) {
            RequestFulfillment::create([
                'request_item_id' => $item->id,
                'asset_id' => $asset->id,
                'quantity_fulfilled' => 1,
                'assigned_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', __('requests.asset_allocation_saved'));
    }
}
