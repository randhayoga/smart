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
            'note' => $note ?? "Menambahkan model baru: {$model->name} ({$model->number})",
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
     * Log item model update, only if tracked attributes actually changed.
     */
    public function logModelUpdated(Model $model, array $originalAttributes, User|int $user, ?string $note = null): ?InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;

        $trackedFields = [
            'number',
            'name',
            'brand_id',
            'uom_id',
            'type_id',
            'specification',
            'min_stock_threshold',
            'image_url',
        ];

        $oldDiff = [];
        $newDiff = [];

        foreach ($trackedFields as $field) {
            $oldVal = $originalAttributes[$field] ?? null;
            $newVal = $model->getAttribute($field);

            if (!$this->areValuesEqual($field, $oldVal, $newVal)) {
                $oldDiff[$field] = $this->formatStateValue($field, $oldVal);
                $newDiff[$field] = $this->formatStateValue($field, $newVal);
            }
        }

        if (empty($newDiff)) {
            return null;
        }

        return InventoryLog::create([
            'model_id' => $model->id,
            'lot_id' => null,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'update',
            'quantity_change' => 0,
            'previous_state' => $oldDiff,
            'new_state' => $newDiff,
            'note' => $note ?? "Memperbarui model {$model->name} ({$model->number})",
            'created_at' => now(),
        ]);
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
            'note' => $note ?? "Menghapus model: {$model->name} ({$model->number})",
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
    public function logLotCreated(Lot $lot, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $lot->loadMissing(['model.uom', 'vendor', 'legacyVendor', 'location', 'project']);

        $quantity = (int) ($lot->initial_quantity ?? $lot->current_quantity ?? 0);
        $uomName = $lot->model?->uom?->name ?? 'item';

        $newState = [
            'id' => $lot->id,
            'number' => $lot->number,
            'model_id' => $lot->model_id,
            'model_name' => $lot->model?->name,
            'initial_quantity' => $lot->initial_quantity,
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
            'note' => $note ?? ("Penerimaan LOT baru {$lot->number} sebanyak {$quantity} {$uomName}" . ($lot->po_number ? " (PO: {$lot->po_number})" : '')),
            'created_at' => now(),
        ]);
    }

    /**
     * Log LOT update, only if tracked attributes actually changed.
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
        ];

        $oldDiff = [];
        $newDiff = [];

        foreach ($trackedFields as $field) {
            $oldVal = $originalAttributes[$field] ?? null;
            $newVal = $lot->getAttribute($field);

            if (!$this->areValuesEqual($field, $oldVal, $newVal)) {
                $oldDiff[$field] = $this->formatStateValue($field, $oldVal);
                $newDiff[$field] = $this->formatStateValue($field, $newVal);
            }
        }

        if (empty($newDiff)) {
            return null;
        }

        $lot->loadMissing('model');

        return InventoryLog::create([
            'model_id' => $lot->model_id,
            'lot_id' => $lot->id,
            'asset_id' => null,
            'user_id' => $userId,
            'action_type' => 'update',
            'quantity_change' => 0,
            'previous_state' => $oldDiff,
            'new_state' => $newDiff,
            'note' => $note ?? "Memperbarui data LOT {$lot->number} pada model " . ($lot->model?->name ?? '-'),
            'created_at' => now(),
        ]);
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
            'note' => $note ?? "Menghapus LOT {$lot->number} dari model " . ($lot->model?->name ?? '-'),
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
