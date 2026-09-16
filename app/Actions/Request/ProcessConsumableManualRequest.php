<?php

namespace App\Actions\Request;

use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\TbProject;
use App\Services\InventoryLogService;

/**
 * Action to process manual stock deduction transactions for consumable items,
 * persisting the completed Request, RequestItem, RequestFulfillment, InventoryLog, and RequestStatusLog.
 */
class ProcessConsumableManualRequest
{
    public function __construct(
        protected InventoryLogService $inventoryLogService
    ) {}

    /**
     * Execute manual consumable deduction transaction.
     *
     * @param AdmUser $admin
     * @param AdmUser $requester
     * @param Barang $barang
     * @param array<array{lot: Lot, quantity: int}> $allocations
     * @param int $totalQty
     * @param string $utilization 'corporate' | 'project'
     * @param int|null $orgId
     * @param int|null $projectId
     * @param string|null $note
     * @return SmartRequest
     */
    public function execute(
        AdmUser $admin,
        AdmUser $requester,
        Barang $barang,
        array $allocations,
        int $totalQty,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        ?string $note
    ): SmartRequest {
        $now = now();

        // 1. Generate nomor request unik secara aman: MMYYYY-XXXX (max 11 chars)
        $monthYear = $now->format('mY');
        $lastRequest = SmartRequest::where('request_number', 'like', $monthYear . '-%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $seq = 1;
        if ($lastRequest) {
            $parts = explode('-', $lastRequest->request_number);
            $seq = ((int) end($parts)) + 1;
        }
        $requestNumber = $monthYear . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        // 2. Buat Request baru dengan status 'success'
        $smartRequest = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $requester->id,
            'approver_id' => $admin->id,
            'utilization' => $utilization,
            'org_id' => $orgId,
            'project_id' => $projectId,
            'reasoning' => $note,
            'status' => 'success',
        ]);

        // 3. Buat RequestItem terkait
        $requestItem = RequestItem::create([
            'request_id' => $smartRequest->id,
            'subcategory_id' => $barang->subcategory_id,
            'barang_id' => $barang->id,
            'quantity_requested' => $totalQty,
            'start_date' => $now,
            'end_date' => null,
            'status' => 'fulfilled',
        ]);

        // 4. Update stok LOT, buat RequestFulfillment dan InventoryLog untuk setiap alokasi LOT
        foreach ($allocations as $alloc) {
            /** @var Lot $lot */
            $lot = $alloc['lot'];
            $deductQty = (int) $alloc['quantity'];

            $prevQty = (int) $lot->current_quantity;
            $newQty = max(0, $prevQty - $deductQty);
            $lot->update(['current_quantity' => $newQty]);

            // Evict atau sesuaikan unconfirmed competing fulfillments pada permintaan lain jika stok berkurang
            $competingFulfillments = RequestFulfillment::where('lot_id', $lot->id)
                ->whereNull('unit_id')
                ->whereNull('assigned_at')
                ->whereNotIn('request_item_id', [$requestItem->id])
                ->get();

            foreach ($competingFulfillments as $cf) {
                if ($newQty <= 0) {
                    $cf->delete();
                } elseif ($cf->quantity_fulfilled > $newQty) {
                    $cf->update(['quantity_fulfilled' => $newQty]);
                }
            }

            // Buat RequestFulfillment dengan timestamps lengkap (assigned_at, confirmed_at, completed_at)
            RequestFulfillment::create([
                'request_item_id' => $requestItem->id,
                'lot_id' => $lot->id,
                'quantity_fulfilled' => $deductQty,
                'assigned_at' => $now,
                'confirmed_at' => $now,
                'completed_at' => $now,
            ]);

            $destinationName = null;
            if ($utilization === 'corporate') {
                $org = $orgId ? HrdOrgchart::find($orgId) : ($requester->hrdEmployee?->orgchart ?? null);
                $destinationName = $org?->org_name ?? $org?->name;
            } elseif ($utilization === 'project') {
                $project = $projectId ? TbProject::find($projectId) : null;
                $destinationName = $project?->project_name ?? $project?->name;
            }

            // Buat InventoryLog dengan action_type 'stock_out' dan quantity_change negatif
            $this->inventoryLogService->logConsumableStockOut(
                barang: $barang,
                lot: $lot,
                deductQty: $deductQty,
                prevQty: $prevQty,
                newQty: $newQty,
                user: $requester,
                utilization: $utilization,
                destinationName: $destinationName,
                reasonNote: $note
            );
        }

        // 5. Catat RequestStatusLog audit trail
        RequestStatusLog::create([
            'request_id' => $smartRequest->id,
            'status_from' => 'draft',
            'status_to' => 'success',
            'changed_by' => $admin->id,
            'note' => "Pengeluaran stok habis pakai dicatat secara manual oleh Admin untuk {$requester->name}.",
            'created_at' => $now,
        ]);

        return $smartRequest;
    }
}
