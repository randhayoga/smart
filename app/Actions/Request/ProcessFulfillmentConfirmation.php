<?php

namespace App\Actions\Request;

use App\Models\Inventory\Lot;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to process confirmation of asset assignments for a request.
 * Handles the decision tree between Full Fulfillment and Partial Fulfillment.
 */
class ProcessFulfillmentConfirmation
{
    /**
     * Execute confirmation of asset assignments.
     *
     * @param SmartRequest $request
     * @param bool $allowPartial
     * @param string|null $note
     * @param User|int $admin
     * @return array
     * @throws ValidationException
     */
    public function execute(SmartRequest $request, bool $allowPartial, ?string $note, $admin): array
    {
        $adminId = $admin instanceof User ? $admin->id : (int) $admin;

        return DB::transaction(function () use ($request, $allowPartial, $note, $adminId) {
            $lockedRequest = SmartRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            $isAllowedStatus = in_array($lockedRequest->status, ['confirm', 'partial'])
                || str_contains($lockedRequest->status, 'partial')
                || str_contains($lockedRequest->status, 'menunggu_serah_terima');

            if (!$isAllowedStatus) {
                throw ValidationException::withMessages([
                    'status' => ["Permintaan dengan status '{$lockedRequest->status}' tidak dapat diproses."],
                ]);
            }

            $lockedRequest->loadMissing([
                'items.fulfillments',
                'items.model.type',
                'items.type',
            ]);

            $totalRequested = 0;
            $totalAssigned = 0;
            $allAssigned = true;

            foreach ($lockedRequest->items as $item) {
                $requested = (int) $item->quantity_requested;
                $totalRequested += $requested;

                $isConsumable = (bool) $item->is_consumable;

                if ($isConsumable) {
                    $assigned = (int) $item->fulfillments
                        ->whereNotNull('lot_id')
                        ->whereNull('asset_id')
                        ->sum('quantity_fulfilled');
                } else {
                    $assigned = $item->fulfillments
                        ->whereNotNull('asset_id')
                        ->count();
                }

                $totalAssigned += $assigned;

                if ($assigned < $requested) {
                    $allAssigned = false;
                }
            }

            if ($totalAssigned === 0) {
                throw ValidationException::withMessages([
                    'allow_partial' => ['Belum ada aset atau model yang dialokasikan. Alokasikan setidaknya 1 item untuk melanjutkan.'],
                ]);
            }

            // Partial fulfillment branch validation
            if (!$allAssigned && !$allowPartial) {
                throw ValidationException::withMessages([
                    'allow_partial' => ['Belum semua barang dialokasikan. Harap konfirmasi pemenuhan sebagian (partial fulfillment) jika ingin melanjutkan.'],
                ]);
            }

            // Commit Phase: Lock allocations, evict duplicate staged assets on other requests, and deduct LOT stock
            $itemIds = $lockedRequest->items->pluck('id')->all();
            $newlyAssignedFulfillments = RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('assigned_at')
                ->get();

            if ($newlyAssignedFulfillments->isNotEmpty()) {
                $now = now();
                RequestFulfillment::whereIn('id', $newlyAssignedFulfillments->pluck('id'))
                    ->update(['assigned_at' => $now]);

                // 1. Evict duplicate asset assignments from competing unconfirmed requests
                $assignedAssetIds = $newlyAssignedFulfillments->pluck('asset_id')->filter()->unique()->all();
                if (!empty($assignedAssetIds)) {
                    RequestFulfillment::whereIn('asset_id', $assignedAssetIds)
                        ->whereNotIn('request_item_id', $itemIds)
                        ->whereNull('assigned_at')
                        ->delete();
                }

                // 2. Deduct physical stock for confirmed LOTs and evict/clamp competing unconfirmed requests
                $lotFulfillments = $newlyAssignedFulfillments->whereNotNull('lot_id')->whereNull('asset_id');
                foreach ($lotFulfillments as $lf) {
                    $lot = Lot::where('id', $lf->lot_id)->lockForUpdate()->first();
                    if (!$lot) {
                        continue;
                    }

                    $deductQty = (int) $lf->quantity_fulfilled;
                    $newStock = max(0, (int) $lot->current_quantity - $deductQty);
                    $lot->update(['current_quantity' => $newStock]);

                    // Competing unconfirmed fulfillments on OTHER requests
                    $competingFulfillments = RequestFulfillment::where('lot_id', $lot->id)
                        ->whereNull('asset_id')
                        ->whereNull('assigned_at')
                        ->whereNotIn('request_item_id', $itemIds)
                        ->get();

                    foreach ($competingFulfillments as $cf) {
                        if ($newStock <= 0) {
                            $cf->delete();
                        } elseif ($cf->quantity_fulfilled > $newStock) {
                            $cf->update(['quantity_fulfilled' => $newStock]);
                        }
                    }
                }
            }

            $oldStatus = $lockedRequest->status;

            if ($allAssigned) {
                // Full Fulfillment
                $newStatus = 'menunggu_serah_terima';
                $lockedRequest->update(['status' => $newStatus]);
                $request->status = $newStatus;

                $logNote = $note ?: 'Admin telah mengalokasikan barang secara penuh';

                RequestStatusLog::create([
                    'request_id' => $lockedRequest->id,
                    'status_from' => $oldStatus,
                    'status_to' => $newStatus,
                    'changed_by' => $adminId,
                    'note' => $logNote,
                ]);

                return [
                    'status' => 'full',
                    'message' => __('requests.fulfillment_full_confirmed'),
                ];
            }

            $newStatus = 'menunggu_serah_terima,partial';
            $lockedRequest->update(['status' => $newStatus]);
            $request->status = $newStatus;

            $logNote = $note ?: 'Admin telah mengalokasikan barang secara parsial';

            RequestStatusLog::create([
                'request_id' => $lockedRequest->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'changed_by' => $adminId,
                'note' => $logNote,
            ]);

            return [
                'status' => 'partial',
                'message' => __('requests.fulfillment_partial_confirmed'),
            ];
        });
    }
}
