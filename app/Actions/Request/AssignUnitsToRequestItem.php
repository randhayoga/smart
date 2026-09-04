<?php

namespace App\Actions\Request;

use App\Models\Inventory\Unit;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to manually assign or update unit allocations for a request item.
 * Preserves already handed-over units and guards against over-allocation or concurrent claims.
 */
class AssignUnitsToRequestItem
{
    /**
     * Execute unit assignment for an item.
     *
     * @param RequestItem $item
     * @param array<int|string> $unitIds Array of selected unit IDs or unit numbers
     * @return Collection
     * @throws ValidationException
     */
    public function execute(RequestItem $item, array $unitIds)
    {
        $item->loadMissing(['request', 'barang', 'subcategory']);

        $quantityRequested = (int) $item->quantity_requested;
        $uniqueUnitIds = array_values(array_unique(array_filter($unitIds)));

        if (count($uniqueUnitIds) > $quantityRequested) {
            throw ValidationException::withMessages([
                'unit_ids' => ["Maksimal {$quantityRequested} unit yang dapat dialokasikan."],
            ]);
        }

        return DB::transaction(function () use ($item, $uniqueUnitIds, $quantityRequested) {
            // 1. Identify already handed-over or locked fulfillments
            $lockedFulfillments = RequestFulfillment::where('request_item_id', $item->id)
                ->where(function ($q) {
                    $q->whereNotNull('handover_id')
                      ->orWhereNotNull('completed_at')
                      ->orWhereHas('unit', fn($uq) => $uq->where('status', 'Dipinjam'));
                })
                ->get();

            $lockedUnitIds = $lockedFulfillments->pluck('unit_id')->filter()->all();
            $lockedCount = count($lockedUnitIds);

            // 2. Newly requested unit IDs must include locked units, or not displace them
            // Ensure no locked unit was removed
            $missingLocked = array_diff($lockedUnitIds, $uniqueUnitIds);
            if (!empty($missingLocked)) {
                // Keep locked units in the set automatically
                $uniqueUnitIds = array_values(array_unique(array_merge($uniqueUnitIds, $lockedUnitIds)));
            }

            if (count($uniqueUnitIds) > $quantityRequested) {
                throw ValidationException::withMessages([
                    'unit_ids' => ["Total alokasi melebihi batas permintaan ({$quantityRequested} unit)."],
                ]);
            }

            // 3. Units to assign that are not already locked
            $newUnitIds = array_diff($uniqueUnitIds, $lockedUnitIds);

            if (!empty($newUnitIds)) {
                // Validate unit existence and suitability
                $units = Unit::with('lot')->whereIn('id', $newUnitIds)->get();

                if ($units->count() !== count($newUnitIds)) {
                    throw ValidationException::withMessages([
                        'unit_ids' => ['Satu atau lebih unit aset tidak ditemukan.'],
                    ]);
                }

                foreach ($units as $unit) {
                    // Check availability status
                    if (strtolower((string)$unit->status) !== 'tersedia') {
                        throw ValidationException::withMessages([
                            'unit_ids' => ["Unit {$unit->number} saat ini berstatus '{$unit->status}' dan tidak dapat dialokasikan."],
                        ]);
                    }

                    // Check that unit belongs to the item's catalog
                    $lotBarangId = $unit->lot?->barang_id;
                    $itemBarangId = $item->barang_id;
                    $itemSubcatId = $item->subcategory_id;

                    $matches = false;
                    if ($itemBarangId && (int)$lotBarangId === (int)$itemBarangId) {
                        $matches = true;
                    } elseif ($itemSubcatId && $unit->lot?->barang?->subcategory_id === $itemSubcatId) {
                        $matches = true;
                    }

                    if (!$matches) {
                        throw ValidationException::withMessages([
                            'unit_ids' => ["Unit {$unit->number} tidak cocok dengan tipe barang yang diminta."],
                        ]);
                    }

                    // Check that unit is not assigned to another active request
                    $isAssignedElsewhere = RequestFulfillment::where('unit_id', $unit->id)
                        ->where('request_item_id', '!=', $item->id)
                        ->whereNull('completed_at')
                        ->lockForUpdate()
                        ->exists();

                    if ($isAssignedElsewhere) {
                        throw ValidationException::withMessages([
                            'unit_ids' => ["Unit {$unit->number} telah dialokasikan pada permintaan lain."],
                        ]);
                    }
                }
            }

            // 4. Remove existing pending (unlocked) fulfillments for this item that are not in uniqueUnitIds
            RequestFulfillment::where('request_item_id', $item->id)
                ->whereNotIn('id', $lockedFulfillments->pluck('id'))
                ->whereNotIn('unit_id', $uniqueUnitIds)
                ->delete();

            // 5. Create fulfillments for new units
            $existingUnitIdsInItem = RequestFulfillment::where('request_item_id', $item->id)
                ->pluck('unit_id')
                ->filter()
                ->all();

            foreach ($newUnitIds as $uid) {
                if (!in_array($uid, $existingUnitIdsInItem)) {
                    $unitModel = Unit::find($uid);
                    RequestFulfillment::create([
                        'request_item_id' => $item->id,
                        'unit_id' => $uid,
                        'lot_id' => $unitModel?->lot_id,
                        'quantity_fulfilled' => 1,
                        'assigned_at' => now(),
                    ]);
                }
            }

            return RequestFulfillment::where('request_item_id', $item->id)->with('unit.lot')->get();
        });
    }
}
