<?php

namespace App\Actions\Request;

use App\Models\AdmUser;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestStatusLog;
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
     * @param AdmUser|int $admin
     * @return array
     * @throws ValidationException
     */
    public function execute(SmartRequest $request, bool $allowPartial, ?string $note, $admin): array
    {
        $adminId = $admin instanceof AdmUser ? $admin->id : (int) $admin;

        return DB::transaction(function () use ($request, $allowPartial, $note, $adminId) {
            $lockedRequest = SmartRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedRequest->status, ['confirm', 'partial'])) {
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

            $oldStatus = $lockedRequest->status;

            if ($allAssigned) {
                // Full Fulfillment
                $newStatus = 'confirm';
                $lockedRequest->update(['status' => $newStatus]);
                $request->status = $newStatus;

                $logNote = $note ?: ($oldStatus === 'partial' 
                    ? 'Alokasi barang tambahan dikonfirmasi oleh Admin (Full Fulfillment).' 
                    : 'Semua alokasi unit berhasil dikonfirmasi oleh Admin (Full Fulfillment).');

                RequestStatusLog::create([
                    'request_id' => $lockedRequest->id,
                    'status_from' => $oldStatus,
                    'status_to' => $newStatus,
                    'changed_by' => $adminId,
                    'note' => $logNote,
                ]);

                return [
                    'status' => 'full',
                    'message' => 'Semua alokasi unit berhasil dikonfirmasi secara penuh (Full Fulfillment). Siap untuk serah terima.',
                ];
            }

            // Partial fulfillment branch
            if (!$allowPartial) {
                throw ValidationException::withMessages([
                    'allow_partial' => ['Belum semua barang dialokasikan. Harap konfirmasi pemenuhan sebagian (partial fulfillment) jika ingin melanjutkan.'],
                ]);
            }

            $newStatus = 'partial';
            $lockedRequest->update(['status' => $newStatus]);
            $request->status = $newStatus;

            $logNote = $note ?: "Disetujui sebagian (Partial) oleh Admin: {$totalAssigned} dari {$totalRequested} unit dialokasikan.";

            RequestStatusLog::create([
                'request_id' => $lockedRequest->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'changed_by' => $adminId,
                'note' => $logNote,
            ]);

            return [
                'status' => 'partial',
                'message' => 'Pemenuhan sebagian (Partial Fulfillment) berhasil dikonfirmasi. Siap untuk serah terima tahap ini.',
            ];
        });
    }
}
