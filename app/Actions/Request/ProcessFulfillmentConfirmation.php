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
 * Action to process confirmation of unit assignments for a request.
 * Handles the decision tree between Full Fulfillment and Partial Fulfillment.
 */
class ProcessFulfillmentConfirmation
{
    /**
     * Execute confirmation of unit assignments.
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
                'items.barang.subcategory.category',
                'items.subcategory.category',
            ]);

            $totalRequested = 0;
            $totalAssigned = 0;
            $allAssigned = true;

            foreach ($lockedRequest->items as $item) {
                $requested = (int) $item->quantity_requested;
                $totalRequested += $requested;

                $isConsumable = (bool) (
                    $item->barang?->subcategory?->category?->is_consumable 
                    ?? $item->subcategory?->category?->is_consumable 
                    ?? false
                );

                if ($isConsumable) {
                    $assigned = (int) $item->fulfillments
                        ->whereNotNull('lot_id')
                        ->whereNull('unit_id')
                        ->sum('quantity_fulfilled');
                } else {
                    $assigned = $item->fulfillments
                        ->whereNotNull('unit_id')
                        ->count();
                }

                $totalAssigned += $assigned;

                if ($assigned < $requested) {
                    $allAssigned = false;
                }
            }

            if ($totalAssigned === 0) {
                throw ValidationException::withMessages([
                    'allow_partial' => ['Belum ada unit atau barang yang dialokasikan. Alokasikan setidaknya 1 unit untuk melanjutkan.'],
                ]);
            }

            // Partial fulfillment branch validation
            if (!$allAssigned && !$allowPartial) {
                throw ValidationException::withMessages([
                    'allow_partial' => ['Belum semua barang dialokasikan. Harap konfirmasi pemenuhan sebagian (partial fulfillment) jika ingin melanjutkan.'],
                ]);
            }

            // Commit Phase: Lock allocations, evict duplicate staged units on other requests, and deduct LOT stock
            $itemIds = $lockedRequest->items->pluck('id')->all();
            $newlyAssignedFulfillments = RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('assigned_at')
                ->get();

            if ($newlyAssignedFulfillments->isNotEmpty()) {
                $now = now();
                RequestFulfillment::whereIn('id', $newlyAssignedFulfillments->pluck('id'))
                    ->update(['assigned_at' => $now]);

                // 1. Evict duplicate unit assignments from competing unconfirmed requests
                $assignedUnitIds = $newlyAssignedFulfillments->pluck('unit_id')->filter()->unique()->all();
                if (!empty($assignedUnitIds)) {
                    RequestFulfillment::whereIn('unit_id', $assignedUnitIds)
                        ->whereNotIn('request_item_id', $itemIds)
                        ->whereNull('assigned_at')
                        ->delete();
                }

                // 2. Deduct physical stock for confirmed LOTs and evict/clamp competing unconfirmed requests
                $lotFulfillments = $newlyAssignedFulfillments->whereNotNull('lot_id')->whereNull('unit_id');
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
                        ->whereNull('unit_id')
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
                    'message' => 'Semua alokasi unit berhasil dikonfirmasi secara penuh (Full Fulfillment). Menunggu Serah Terima.',
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
                'message' => 'Pemenuhan sebagian (Partial Fulfillment) berhasil dikonfirmasi. Menunggu Serah Terima (Parsial).',
            ];
        });
    }
}
