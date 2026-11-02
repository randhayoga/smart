<?php

namespace App\Services;

use App\Models\User;
use App\Models\Inventory\Model;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use Carbon\Carbon;

/**
 * Inventory Log Service managing centralized, clean audit trail records for Model and LOT operations.
 */
class InventoryLogService
{
    /**
     * Log item model creation.
     */
    public function logModelCreated(Model $model, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $model->loadMissing(['brand', 'uom', 'type']);

        $newState = [
            'id' => $model->id,
            'number' => $model->number,
            'name' => $model->name,
            'brand' => $model->brand?->name,
            'uom' => $model->uom?->name,
            'type' => $model->type?->name,
            'specification' => $model->specification,
            'min_stock_threshold' => $model->min_stock_threshold,
        ];

        return InventoryLog::create([
            'model_id' => $model->id,
            'lot_id' => null,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'create',
            'quantity_change' => 0,
            'previous_state' => null,
            'new_state' => $newState,
            'note' => $note ?? "Menambahkan model baru: {$model->name}",
            'created_at' => now(),
        ]);
    }

    /**
     * Alias for logModelCreated.
     */
    public function logBarangCreated(Model $model, User|int $user, ?string $note = null): InventoryLog
    {
        return $this->logModelCreated($model, $user, $note);
    }

    /**
     * Log item model update, creating detailed individual logs for each tracked attribute that changed.
     */
    public function logModelUpdated(Model $model, array $originalAttributes, User|int $user, ?string $note = null): ?InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;

        $trackedFields = [
            'name',
            'brand_id',
            'uom_id',
            'type_id',
            'specification',
            'min_stock_threshold',
        ];

        $model->loadMissing(['brand', 'uom', 'type']);

        $logsCreated = [];

        foreach ($trackedFields as $field) {
            $oldVal = $originalAttributes[$field] ?? null;
            $newVal = $model->getAttribute($field);

            if ($this->areValuesEqual($field, $oldVal, $newVal)) {
                continue;
            }

            $oldFormatted = $this->formatStateValue($field, $oldVal);
            $newFormatted = $this->formatStateValue($field, $newVal);

            $fieldNote = $this->buildModelFieldChangeNote($model, $field, $oldVal, $newVal);

            $log = InventoryLog::create([
                'model_id' => $model->id,
                'lot_id' => null,
                'asset_id' => null,
                'user_id' => $userId,
                'action_type' => 'update',
                'quantity_change' => 0,
                'previous_state' => [$field => $oldFormatted],
                'new_state' => [$field => $newFormatted],
                'note' => $note ?? $fieldNote,
                'created_at' => now(),
            ]);

            $logsCreated[] = $log;
        }

        return !empty($logsCreated) ? end($logsCreated) : null;
    }

    /**
     * Build informative Indonesian note for an attribute update on Model.
     */
    private function buildModelFieldChangeNote(Model $model, string $field, mixed $oldVal, mixed $newVal): string
    {
        switch ($field) {
            case 'name':
                $oldName = $oldVal ?: '-';
                $newName = $newVal ?: '-';
                return "Mengubah nama model dari '{$oldName}' menjadi '{$newName}'";

            case 'brand_id':
                $oldBrand = $oldVal ? (\App\Models\Master\Brand::find($oldVal)?->name ?? $oldVal) : '-';
                $newBrand = $newVal ? (\App\Models\Master\Brand::find($newVal)?->name ?? $newVal) : '-';
                return "Mengubah merek dari '{$oldBrand}' menjadi '{$newBrand}'";

            case 'uom_id':
                $oldUom = $oldVal ? (\App\Models\Master\Uom::find($oldVal)?->name ?? $oldVal) : '-';
                $newUom = $newVal ? (\App\Models\Master\Uom::find($newVal)?->name ?? $newVal) : '-';
                return "Mengubah satuan (UoM) dari '{$oldUom}' menjadi '{$newUom}'";

            case 'type_id':
                $oldType = $oldVal ? (\App\Models\Master\Subcategory::find($oldVal)?->name ?? $oldVal) : '-';
                $newType = $newVal ? (\App\Models\Master\Subcategory::find($newVal)?->name ?? $newVal) : '-';
                return "Mengubah jenis model dari '{$oldType}' menjadi '{$newType}'";

            case 'specification':
                $oldSpec = $oldVal ?: '-';
                $newSpec = $newVal ?: '-';
                return "Mengubah spesifikasi dari '{$oldSpec}' menjadi '{$newSpec}'";

            case 'min_stock_threshold':
                $oldTh = $oldVal !== null && $oldVal !== '' ? (string)$oldVal : '0';
                $newTh = $newVal !== null && $newVal !== '' ? (string)$newVal : '0';
                return "Mengubah batas minimum stok dari '{$oldTh}' menjadi '{$newTh}'";

            default:
                $oldStr = $oldVal !== null ? (string)$oldVal : '-';
                $newStr = $newVal !== null ? (string)$newVal : '-';
                return "Memperbarui {$field} dari '{$oldStr}' menjadi '{$newStr}'";
        }
    }

    /**
     * Alias for logModelUpdated.
     */
    public function logBarangUpdated(Model $model, array $originalAttributes, User|int $user, ?string $note = null): ?InventoryLog
    {
        return $this->logModelUpdated($model, $originalAttributes, $user, $note);
    }

    /**
     * Prepare existing logs for safe deletion (unlinking foreign keys) and log the deletion event.
     */
    public function prepareAndLogModelDeleted(Model $model, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $model->loadMissing(['brand', 'uom', 'type']);

        $previousState = [
            'id' => $model->id,
            'number' => $model->number,
            'name' => $model->name,
            'brand' => $model->brand?->name,
            'uom' => $model->uom?->name,
            'type' => $model->type?->name,
            'specification' => $model->specification,
        ];

        // Unlink foreign key in prior logs to prevent SQL Server foreign key constraint conflicts on delete
        InventoryLog::where('model_id', $model->id)->update(['model_id' => null]);

        return InventoryLog::create([
            'model_id' => null,
            'lot_id' => null,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'delete',
            'quantity_change' => 0,
            'previous_state' => $previousState,
            'new_state' => null,
            'note' => $note ?? "Menghapus model: {$model->name}",
            'created_at' => now(),
        ]);
    }

    /**
     * Alias for prepareAndLogModelDeleted.
     */
    public function prepareAndLogBarangDeleted(Model $model, User|int $user, ?string $note = null): InventoryLog
    {
        return $this->prepareAndLogModelDeleted($model, $user, $note);
    }

    /**
     * Log LOT creation as inbound stock (stock_in).
     */
    public function logLotCreated(Lot $lot, User|int $user, ?string $note = null, ?int $quantityOverride = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $lot->loadMissing(['model.uom', 'vendor', 'legacyVendor', 'location', 'project']);

        $quantity = $quantityOverride !== null
            ? (int) $quantityOverride
            : (int) ($lot->initial_quantity ?? $lot->current_quantity ?? $lot->assets()->count() ?? 0);
        $uomName = $lot->model?->uom?->name ?? 'item';

        $newState = [
            'id' => $lot->id,
            'number' => $lot->number,
            'model_id' => $lot->model_id,
            'model_name' => $lot->model?->name,
            'initial_quantity' => $quantity,
            'current_quantity' => $lot->current_quantity,
            'po_number' => $lot->po_number,
            'purchase_grant_date' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
            'vendor' => $lot->vendor_name ?? null,
            'vendor_id' => $lot->vendor_id,
            'legacy_vendor_id' => $lot->legacy_vendor_id,
            'burden' => $lot->burden,
            'project' => $lot->project?->project_name,
            'entry_type' => $lot->entry_type,
            'memo_number' => $lot->memo_number,
        ];

        return InventoryLog::create([
            'model_id' => $lot->model_id,
            'lot_id' => $lot->id,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'stock_in',
            'quantity_change' => $quantity,
            'previous_state' => null,
            'new_state' => $newState,
            'note' => $note ?? ("Penerimaan LOT baru sebanyak {$quantity} {$uomName}" . ($lot->po_number ? " (PO: {$lot->po_number})" : ($lot->memo_number ? " (Memo: {$lot->memo_number})" : ''))),
            'created_at' => now(),
        ]);
    }

    /**
     * Log LOT update, creating detailed individual logs for each tracked attribute that changed.
     */
    public function logLotUpdated(Lot $lot, array $originalAttributes, User|int $user, ?string $note = null): ?InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;

        $trackedFields = [
            'organizer_id',
            'vendor_id',
            'legacy_vendor_id',
            'po_number',
            'purchase_grant_date',
            'burden',
            'project_id',
            'entry_type',
            'memo_number',
            'location_id',
            'unit_price',
        ];

        $lot->loadMissing(['model', 'vendor', 'legacyVendor', 'organizer', 'location', 'project']);

        $logsCreated = [];

        foreach ($trackedFields as $field) {
            $oldVal = $originalAttributes[$field] ?? null;
            $newVal = $lot->getAttribute($field);

            if ($this->areValuesEqual($field, $oldVal, $newVal)) {
                continue;
            }

            $oldFormatted = $this->formatStateValue($field, $oldVal);
            $newFormatted = $this->formatStateValue($field, $newVal);

            // Construct specific, informative note in Indonesian
            $fieldNote = $this->buildLotFieldChangeNote($lot, $field, $oldVal, $newVal);

            $log = InventoryLog::create([
                'model_id' => $lot->model_id,
                'lot_id' => $lot->id,
                'asset_id' => null,
                'user_id' => $userId,
                'action_type' => 'update',
                'quantity_change' => 0,
                'previous_state' => [$field => $oldFormatted],
                'new_state' => [$field => $newFormatted],
                'note' => $note ?? $fieldNote,
                'created_at' => now(),
            ]);

            $logsCreated[] = $log;
        }

        return !empty($logsCreated) ? end($logsCreated) : null;
    }

    /**
     * Build informative Indonesian note for an attribute update on LOT.
     */
    private function buildLotFieldChangeNote(Lot $lot, string $field, mixed $oldVal, mixed $newVal): string
    {
        switch ($field) {
            case 'organizer_id':
                $oldOrg = $oldVal ? (\App\Models\Master\Organizer::find($oldVal)?->name ?? $oldVal) : '-';
                $newOrg = $newVal ? (\App\Models\Master\Organizer::find($newVal)?->name ?? $newVal) : '-';
                return "Mengubah pengelola dari '{$oldOrg}' menjadi '{$newOrg}'";

            case 'vendor_id':
            case 'legacy_vendor_id':
                $oldVen = $oldVal ? (\App\Models\Master\Vendor::find($oldVal)?->name ?? $oldVal) : '-';
                $newVen = $newVal ? (\App\Models\Master\Vendor::find($newVal)?->name ?? $newVal) : '-';
                return "Mengubah vendor dari '{$oldVen}' menjadi '{$newVen}'";

            case 'po_number':
                $oldPo = $oldVal ?: '-';
                $newPo = $newVal ?: '-';
                return "Mengubah nomor PO dari '{$oldPo}' menjadi '{$newPo}'";

            case 'purchase_grant_date':
                $oldDate = $oldVal instanceof \DateTimeInterface ? $oldVal->format('d/m/Y') : ($oldVal ? substr((string)$oldVal, 0, 10) : '-');
                $newDate = $newVal instanceof \DateTimeInterface ? $newVal->format('d/m/Y') : ($newVal ? substr((string)$newVal, 0, 10) : '-');
                return "Mengubah tanggal pembelian/hibah dari '{$oldDate}' menjadi '{$newDate}'";

            case 'entry_type':
                $oldType = $oldVal ?: '-';
                $newType = $newVal ?: '-';
                return "Mengubah jenis entri dari '{$oldType}' menjadi '{$newType}'";

            case 'memo_number':
                $oldMemo = $oldVal ?: '-';
                $newMemo = $newVal ?: '-';
                return "Mengubah nomor memo dari '{$oldMemo}' menjadi '{$newMemo}'";

            case 'burden':
                $oldBurden = $oldVal ?: '-';
                $newBurden = $newVal ?: '-';
                return "Mengubah pembebanan dari '{$oldBurden}' menjadi '{$newBurden}'";

            case 'project_id':
                $oldPrj = $oldVal ? (\App\Models\Master\Project::find($oldVal)?->project_name ?? $oldVal) : '-';
                $newPrj = $newVal ? (\App\Models\Master\Project::find($newVal)?->project_name ?? $newVal) : '-';
                return "Mengubah proyek dari '{$oldPrj}' menjadi '{$newPrj}'";

            case 'location_id':
                $oldLoc = $oldVal ? (\App\Models\Master\Location::find($oldVal)?->full_name ?? \App\Models\Master\Location::find($oldVal)?->name ?? $oldVal) : '-';
                $newLoc = $newVal ? (\App\Models\Master\Location::find($newVal)?->full_name ?? \App\Models\Master\Location::find($newVal)?->name ?? $newVal) : '-';
                return "Mengubah lokasi consumable dari '{$oldLoc}' menjadi '{$newLoc}'";

            case 'unit_price':
                $oldPrice = $oldVal !== null ? number_format((float) $oldVal, 0, ',', '.') : '-';
                $newPrice = $newVal !== null ? number_format((float) $newVal, 0, ',', '.') : '-';
                return "Mengubah harga satuan dari 'Rp {$oldPrice}' menjadi 'Rp {$newPrice}'";

            default:
                $oldStr = $oldVal !== null ? (string)$oldVal : '-';
                $newStr = $newVal !== null ? (string)$newVal : '-';
                return "Memperbarui {$field} dari '{$oldStr}' menjadi '{$newStr}'";
        }
    }

    /**
     * Prepare existing LOT logs for safe deletion (unlinking foreign keys) and log the deletion event.
     */
    public function prepareAndLogLotDeleted(Lot $lot, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $lot->loadMissing(['model', 'vendor', 'legacyVendor', 'location']);

        $previousState = [
            'id' => $lot->id,
            'number' => $lot->number,
            'model_id' => $lot->model_id,
            'model_name' => $lot->model?->name,
            'initial_quantity' => $lot->initial_quantity,
            'current_quantity' => $lot->current_quantity,
            'po_number' => $lot->po_number,
        ];

        // Unlink foreign key in prior logs to prevent SQL Server foreign key constraint conflicts on delete
        InventoryLog::where('lot_id', $lot->id)->update(['lot_id' => null]);

        $currentQty = (int) ($lot->current_quantity ?? 0);

        return InventoryLog::create([
            'model_id' => $lot->model_id,
            'lot_id' => null,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'delete',
            'quantity_change' => -$currentQty,
            'previous_state' => $previousState,
            'new_state' => null,
            'note' => $note ?? ("Menghapus LOT pada model " . ($lot->model?->name ?? '-')),
            'created_at' => now(),
        ]);
    }

    /**
     * Log consumable stock deduction (stock_out) with formatted note.
     */
    public function logConsumableStockOut(
        Model $model,
        Lot $lot,
        int $deductQty,
        int $prevQty,
        int $newQty,
        User|int $user,
        string $utilization,
        ?string $destinationName,
        ?string $reasonNote,
        \Carbon\CarbonInterface|\DateTimeInterface|string|null $createdAt = null
    ): InventoryLog {
        $userId = $user instanceof User ? $user->id : $user;
        $utilizationLabel = ucfirst(strtolower($utilization));
        $trimmedReason = trim($reasonNote ?? '');
        $catatan = !empty($trimmedReason) ? "\"{$trimmedReason}\"" : '"-"';
        $destPart = !empty($destinationName) ? " {$destinationName}" : '';

        $note = "Permintaan untuk {$utilizationLabel}{$destPart}, dengan catatan {$catatan}";

        return InventoryLog::create([
            'model_id' => $model->id,
            'lot_id' => $lot->id,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'stock_out',
            'quantity_change' => -$deductQty,
            'previous_state' => ['current_quantity' => $prevQty],
            'new_state' => ['current_quantity' => $newQty],
            'note' => $note,
            'created_at' => $createdAt ? Carbon::parse($createdAt) : now(),
        ]);
    }

    /**
     * Check if two attribute values are logically identical, handling date formatting and numeric casting.
     */
    private function areValuesEqual(string $field, mixed $oldVal, mixed $newVal): bool
    {
        if ($oldVal === null && $newVal === null) {
            return true;
        }
        if ($oldVal === null || $newVal === null) {
            return false;
        }

        if (in_array($field, ['unit_price', 'min_stock_threshold', 'initial_quantity', 'current_quantity'], true)) {
            return (float) $oldVal === (float) $newVal;
        }

        if ($field === 'purchase_grant_date') {
            $oldDate = $oldVal instanceof \DateTimeInterface ? $oldVal->format('Y-m-d') : substr((string) $oldVal, 0, 10);
            $newDate = $newVal instanceof \DateTimeInterface ? $newVal->format('Y-m-d') : substr((string) $newVal, 0, 10);
            return $oldDate === $newDate;
        }

        return (string) $oldVal === (string) $newVal;
    }

    /**
     * Format attribute values cleanly for JSON state snapshots.
     */
    private function formatStateValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($field === 'purchase_grant_date') {
            return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
        }

        if ($field === 'unit_price') {
            return (float) $value;
        }

        if (in_array($field, ['min_stock_threshold', 'initial_quantity', 'current_quantity'], true)) {
            return (int) $value;
        }

        return $value;
    }
}
