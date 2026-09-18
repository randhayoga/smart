<?php

namespace App\Services;

use App\Models\User;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use Carbon\Carbon;

/**
 * Inventory Log Service managing centralized, clean audit trail records for Barang and LOT operations.
 */
class InventoryLogService
{
    /**
     * Log item type (Barang) creation.
     */
    public function logBarangCreated(Barang $barang, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $barang->loadMissing(['brand', 'uom', 'subcategory']);

        $newState = [
            'id' => $barang->id,
            'number' => $barang->number,
            'name' => $barang->name,
            'brand' => $barang->brand?->name,
            'uom' => $barang->uom?->name,
            'subcategory' => $barang->subcategory?->name,
            'specification' => $barang->specification,
            'min_stock_threshold' => $barang->min_stock_threshold,
        ];

        return InventoryLog::create([
            'barang_id' => $barang->id,
            'lot_id' => null,
            'unit_id' => null,
            'user_id' => $userId,
            'action_type' => 'create',
            'quantity_change' => 0,
            'previous_state' => null,
            'new_state' => $newState,
            'note' => $note ?? "Menambahkan tipe barang baru: {$barang->name} ({$barang->number})",
            'created_at' => now(),
        ]);
    }

    /**
     * Log item type (Barang) update, only if tracked attributes actually changed.
     */
    public function logBarangUpdated(Barang $barang, array $originalAttributes, User|int $user, ?string $note = null): ?InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;

        $trackedFields = [
            'number',
            'name',
            'brand_id',
            'uom_id',
            'subcategory_id',
            'specification',
            'min_stock_threshold',
            'image_url',
        ];

        $oldDiff = [];
        $newDiff = [];

        foreach ($trackedFields as $field) {
            $oldVal = $originalAttributes[$field] ?? null;
            $newVal = $barang->getAttribute($field);

            if (!$this->areValuesEqual($field, $oldVal, $newVal)) {
                $oldDiff[$field] = $this->formatStateValue($field, $oldVal);
                $newDiff[$field] = $this->formatStateValue($field, $newVal);
            }
        }

        if (empty($newDiff)) {
            return null;
        }

        return InventoryLog::create([
            'barang_id' => $barang->id,
            'lot_id' => null,
            'unit_id' => null,
            'user_id' => $userId,
            'action_type' => 'update',
            'quantity_change' => 0,
            'previous_state' => $oldDiff,
            'new_state' => $newDiff,
            'note' => $note ?? "Memperbarui tipe barang {$barang->name} ({$barang->number})",
            'created_at' => now(),
        ]);
    }

    /**
     * Prepare existing logs for safe deletion (unlinking foreign keys) and log the deletion event.
     */
    public function prepareAndLogBarangDeleted(Barang $barang, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $barang->loadMissing(['brand', 'uom', 'subcategory']);

        $previousState = [
            'id' => $barang->id,
            'number' => $barang->number,
            'name' => $barang->name,
            'brand' => $barang->brand?->name,
            'uom' => $barang->uom?->name,
            'subcategory' => $barang->subcategory?->name,
            'specification' => $barang->specification,
        ];

        // Unlink foreign key in prior logs to prevent SQL Server foreign key constraint conflicts on delete
        InventoryLog::where('barang_id', $barang->id)->update(['barang_id' => null]);

        return InventoryLog::create([
            'barang_id' => null,
            'lot_id' => null,
            'unit_id' => null,
            'user_id' => $userId,
            'action_type' => 'delete',
            'quantity_change' => 0,
            'previous_state' => $previousState,
            'new_state' => null,
            'note' => $note ?? "Menghapus tipe barang: {$barang->name} ({$barang->number})",
            'created_at' => now(),
        ]);
    }

    /**
     * Log LOT creation as inbound stock (stock_in).
     */
    public function logLotCreated(Lot $lot, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $lot->loadMissing(['barang.uom', 'vendor', 'location', 'project']);

        $quantity = (int) ($lot->initial_quantity ?? $lot->current_quantity ?? 0);
        $uomName = $lot->barang?->uom?->name ?? 'item';

        $newState = [
            'id' => $lot->id,
            'number' => $lot->number,
            'barang_id' => $lot->barang_id,
            'barang_name' => $lot->barang?->name,
            'initial_quantity' => $lot->initial_quantity,
            'current_quantity' => $lot->current_quantity,
            'po_number' => $lot->po_number,
            'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('Y-m-d') : null,
            'vendor' => $lot->vendor?->name,
            'location' => $lot->location?->name,
            'burden' => $lot->burden,
            'project' => $lot->project?->project_name,
            'unit_price' => $lot->unit_price,
        ];

        return InventoryLog::create([
            'barang_id' => $lot->barang_id,
            'lot_id' => $lot->id,
            'unit_id' => null,
            'user_id' => $userId,
            'action_type' => 'stock_in',
            'quantity_change' => $quantity,
            'previous_state' => null,
            'new_state' => $newState,
            'note' => $note ?? "Penerimaan LOT baru {$lot->number} sebanyak {$quantity} {$uomName} (PO: {$lot->po_number})",
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
            'location_id',
            'po_number',
            'date_of_receipt',
            'unit_price',
            'burden',
            'project_id',
            'image_url',
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

        $lot->loadMissing('barang');

        return InventoryLog::create([
            'barang_id' => $lot->barang_id,
            'lot_id' => $lot->id,
            'unit_id' => null,
            'user_id' => $userId,
            'action_type' => 'update',
            'quantity_change' => 0,
            'previous_state' => $oldDiff,
            'new_state' => $newDiff,
            'note' => $note ?? "Memperbarui data LOT {$lot->number} pada tipe barang " . ($lot->barang?->name ?? '-'),
            'created_at' => now(),
        ]);
    }

    /**
     * Prepare existing LOT logs for safe deletion (unlinking foreign keys) and log the deletion event.
     */
    public function prepareAndLogLotDeleted(Lot $lot, User|int $user, ?string $note = null): InventoryLog
    {
        $userId = $user instanceof User ? $user->id : $user;
        $lot->loadMissing(['barang', 'vendor', 'location']);

        $previousState = [
            'id' => $lot->id,
            'number' => $lot->number,
            'barang_id' => $lot->barang_id,
            'barang_name' => $lot->barang?->name,
            'initial_quantity' => $lot->initial_quantity,
            'current_quantity' => $lot->current_quantity,
            'po_number' => $lot->po_number,
        ];

        // Unlink foreign key in prior logs to prevent SQL Server foreign key constraint conflicts on delete
        InventoryLog::where('lot_id', $lot->id)->update(['lot_id' => null]);

        $currentQty = (int) ($lot->current_quantity ?? 0);

        return InventoryLog::create([
            'barang_id' => $lot->barang_id,
            'lot_id' => null,
            'unit_id' => null,
            'user_id' => $userId,
            'action_type' => 'delete',
            'quantity_change' => -$currentQty,
            'previous_state' => $previousState,
            'new_state' => null,
            'note' => $note ?? "Menghapus LOT {$lot->number} dari tipe barang " . ($lot->barang?->name ?? '-'),
            'created_at' => now(),
        ]);
    }

    /**
     * Log consumable stock deduction (stock_out) with formatted note.
     */
    public function logConsumableStockOut(
        Barang $barang,
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
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'unit_id' => null,
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

        if ($field === 'date_of_receipt') {
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

        if ($field === 'date_of_receipt') {
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
