<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Partial Request Controller managing partially fulfilled requests awaiting completion.
 */
class AdminPartialRequestController extends Controller
{
    /**
     * Eager-loaded relationships for listing.
     */
    protected array $relations = [
        'user',
        'approver',
        'department',
        'project',
        'items.fulfillments',
        'items.barang.subcategory.category',
        'items.subcategory.category',
    ];

    /**
     * Display a list of partially fulfilled requests ('partial' status).
     */
    public function index(Request $request): JsonResponse|Response
    {
        $query = SmartRequest::with($this->relations)
            ->where('status', 'partial')
            ->orderBy('id', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->input('type')) {
            if ($type === 'pinjam' || $type === 'peminjaman') {
                $query->whereHas('items', fn($iq) => $iq->whereNotNull('start_date'));
            } elseif ($type === 'permintaan' || $type === 'habis_pakai') {
                $query->whereDoesntHave('items', fn($iq) => $iq->whereNotNull('start_date'));
            }
        }

        $requests = $query->get()->map(function (SmartRequest $req) {
            $totalRequested = 0;
            $totalFulfilled = 0;

            foreach ($req->items as $item) {
                $totalRequested += (int) $item->quantity_requested;
                $isConsumable = (bool) (
                    $item->barang?->subcategory?->category?->is_consumable 
                    ?? $item->subcategory?->category?->is_consumable 
                    ?? false
                );

                if ($isConsumable) {
                    $totalFulfilled += (int) $item->fulfillments
                        ->whereNotNull('lot_id')
                        ->whereNull('unit_id')
                        ->sum('quantity_fulfilled');
                } else {
                    $totalFulfilled += $item->fulfillments
                        ->whereNotNull('unit_id')
                        ->count();
                }
            }

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
                'uuid' => $req->uuid,
                'number' => $req->request_number,
                'requester' => $req->user?->name ?? '-',
                'approver' => $req->approver?->name ?? '-',
                'type' => $req->type_key,
                'typeLabel' => $req->type_name,
                'destination' => $req->destination_name,
                'pemanfaatan' => $req->utilization,
                'pemanfaatanDetail' => $pemanfaatanDetail,
                'total_items' => $req->items->count(),
                'total_requested' => $totalRequested,
                'total_fulfilled' => $totalFulfilled,
                'is_fully_fulfilled' => $totalRequested > 0 && $totalFulfilled >= $totalRequested,
                'status' => 'Partial',
                'raw_status' => $req->status,
                'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
                'created_at' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
                'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
                'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            ];
        });

        if ($request->wantsJson()) {
            return response()->json(['requests' => $requests]);
        }

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/PermintaanAktif', [
            'user' => $request->user(),
            'activeTab' => 'Parsial',
            'partialRequests' => $requests,
            'filters' => $request->only(['search', 'type']),
        ]);
    }
}
