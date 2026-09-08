<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmartRequestItemResource;
use App\Http\Resources\SmartRequestResource;
use App\Models\Request\Request as SmartRequest;
use App\Services\InventoryStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller consolidating all active request lifecycle stages into the "Permintaan Aktif" view.
 * Stages: Inbox, Perlu Alokasi, Parsial, Serah Terima, Lacak Peminjaman, and Pengembalian.
 */
class AdminActiveRequestController extends Controller
{
    /**
     * Eager-loaded relations for request fulfillment listing.
     */
    protected array $fulfillmentRelations = [
        'user',
        'approver',
        'department',
        'project',
        'items.fulfillments',
        'items.barang.subcategory.category',
        'items.subcategory.category',
    ];

    /**
     * Eager-loaded relations for approved requests (inbox).
     */
    protected array $approvedRelations = [
        'user',
        'approver',
        'approval.approver',
        'items.barang.subcategory.category',
        'items.barang.brand',
        'items.barang.uom',
        'items.subcategory.category',
        'items.subcategory.barangs.uom',
        'project',
        'department',
    ];

    /**
     * Display the consolidated Permintaan Aktif page.
     */
    public function index(Request $request, InventoryStockService $stockService): JsonResponse|Response
    {
        $activeTab = $request->input('tab', 'Inbox');

        if ($request->wantsJson()) {
            return response()->json([
                'activeTab' => $activeTab,
                'inboxRequests' => $this->getInboxRequests($stockService),
                'confirmedRequests' => $this->getConfirmedRequests(),
                'partialRequests' => $this->getPartialRequests(),
                'handovers' => $this->getHandovers(),
                'borrowedList' => $this->getBorrowedList(),
                'returnsList' => $this->getReturnsList(),
            ]);
        }

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/PermintaanAktif', [
            'user' => $request->user(),
            'activeTab' => $activeTab,
            'inboxRequests' => ($activeTab === 'Inbox') ? fn() => $this->getInboxRequests($stockService) : Inertia::lazy(fn() => $this->getInboxRequests($stockService)),
            'confirmedRequests' => ($activeTab === 'Perlu Alokasi') ? fn() => $this->getConfirmedRequests() : Inertia::lazy(fn() => $this->getConfirmedRequests()),
            'partialRequests' => ($activeTab === 'Parsial') ? fn() => $this->getPartialRequests() : Inertia::lazy(fn() => $this->getPartialRequests()),
            'handovers' => ($activeTab === 'Serah Terima') ? fn() => $this->getHandovers() : Inertia::lazy(fn() => $this->getHandovers()),
            'borrowedList' => ($activeTab === 'Lacak Peminjaman') ? fn() => $this->getBorrowedList() : Inertia::lazy(fn() => $this->getBorrowedList()),
            'returnsList' => ($activeTab === 'Pengembalian') ? fn() => $this->getReturnsList() : Inertia::lazy(fn() => $this->getReturnsList()),
        ]);
    }

    /**
     * Fetch approved requests awaiting admin confirmation (Inbox).
     */
    public function getInboxRequests(InventoryStockService $stockService): array
    {
        request()->attributes->set('with_stock', true);

        $requests = SmartRequest::with($this->approvedRelations)
            ->where('status', 'approve')
            ->orderBy('id', 'desc')
            ->get();

        $allItems = $requests->pluck('items')->flatten();
        $stockMap = $stockService->getBatchAvailableStock($allItems);
        SmartRequestItemResource::setBatchStockMap($stockMap);

        $resolved = SmartRequestResource::collection($requests)->resolve();

        SmartRequestItemResource::setBatchStockMap(null);

        return $resolved;
    }

    /**
     * Fetch newly confirmed requests awaiting allocation (Perlu Alokasi).
     */
    public function getConfirmedRequests(): array
    {
        return SmartRequest::with($this->fulfillmentRelations)
            ->where('status', 'confirm')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn(SmartRequest $req) => $this->mapFulfillmentListItem($req))
            ->values()
            ->toArray();
    }

    /**
     * Fetch partially fulfilled requests (Parsial).
     */
    public function getPartialRequests(): array
    {
        return SmartRequest::with($this->fulfillmentRelations)
            ->where('status', 'partial')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn(SmartRequest $req) => $this->mapFulfillmentListItem($req))
            ->values()
            ->toArray();
    }

    /**
     * Fetch handover scheduled or pending requests (Serah Terima).
     */
    public function getHandovers(): array
    {
        return SmartRequest::with(['user', 'handover'])
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
            })
            ->values()
            ->toArray();
    }

    /**
     * Fetch actively borrowed assets (Lacak Peminjaman).
     */
    public function getBorrowedList(): array
    {
        return SmartRequest::with(['user', 'handover', 'items'])
            ->where('status', 'borrow')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $daysLeft = '-';
                $dueDateStr = '-';
                if ($req->end_date) {
                    $dueDateStr = $req->end_date->format('d-m-Y H:i');
                    $diff = now()->diffInDays($req->end_date, false);
                    $daysLeft = $diff >= 0 ? (string)(int)$diff : 'Telat ' . abs((int)$diff) . ' hari';
                }

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
            })
            ->values()
            ->toArray();
    }

    /**
     * Fetch pending returns (Pengembalian).
     */
    public function getReturnsList(): array
    {
        return SmartRequest::with(['user', 'return', 'items'])
            ->where('status', 'return')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $ret = $req->return;
                $methodStr = 'Belum diatur';
                $timeStr = '-';
                $locStr = '-';

                if ($ret) {
                    $methodStr = $ret->method === 'self' ? 'Kembalikan sendiri' : 'Diantar';
                    $timeStr = $ret->scheduled_date ? $ret->scheduled_date->format('d-m-Y H:i') : '-';
                    $locStr = $ret->location ?? '-';
                }

                $daysLeft = '-';
                if ($req->end_date) {
                    $diff = now()->diffInDays($req->end_date, false);
                    $daysLeft = $diff >= 0 ? (string)$diff : 'Telat ' . abs($diff) . ' hari';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'borrower' => $req->user->name ?? '-',
                    'method' => $methodStr,
                    'returnTime' => $timeStr,
                    'location' => $locStr,
                    'daysLeft' => $daysLeft,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Helper to map fulfillment items for listing.
     */
    protected function mapFulfillmentListItem(SmartRequest $req): array
    {
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
            'status' => $req->status === 'partial' ? 'Partial' : 'Dikonfirmasi Admin',
            'raw_status' => $req->status,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'created_at' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
        ];
    }
}
