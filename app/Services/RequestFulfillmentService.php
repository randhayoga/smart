<?php

namespace App\Services;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Request Fulfillment Service managing automatic FIFO fulfillment and unit availability queries.
 */
class RequestFulfillmentService
{
    /**
     * Query available units for a given request item.
     *
     * @param RequestItem $item
     * @param bool $includeCurrentItemAssignments Whether to include units already assigned to this item
     * @return Builder
     */
    public function getAvailableUnitsQuery(RequestItem $item, bool $includeCurrentItemAssignments = false): Builder
    {
        $query = Unit::query()
            ->with(['lot.barang.brand', 'location', 'floor', 'room'])
            ->whereRaw("LOWER(units.status) = 'tersedia'");

        // Filter by item barang or subcategory
        $query->whereHas('lot', function ($lq) use ($item) {
            if ($item->barang_id) {
                $lq->where('barang_id', $item->barang_id);
            } else {
                $lq->whereHas('barang', fn($bq) => $bq->where('subcategory_id', $item->subcategory_id));
            }
        });

        // Ensure unit is not assigned to another active request fulfillment
        $query->whereDoesntHave('fulfillments', function ($fq) use ($item, $includeCurrentItemAssignments) {
            $fq->whereNull('completed_at');
            if ($includeCurrentItemAssignments) {
                $fq->where('request_item_id', '!=', $item->id);
            }
        });

        // FIFO ordering: oldest lot receipt date first, then creation timestamp
        $query->leftJoin('lots', 'units.lot_id', '=', 'lots.id')
            ->select('units.*')
            ->orderBy('lots.date_of_receipt', 'asc')
            ->orderBy('units.created_at', 'asc')
            ->orderBy('units.id', 'asc');

        return $query;
    }

    /**
     * Auto-fulfill an asset (non-consumable) item using FIFO.
     *
     * @param RequestItem $item
     * @return int Count of newly fulfilled units
     */
    public function autoFulfillAssetItem(RequestItem $item): int
    {
        $isConsumable = (bool) (
            $item->barang?->subcategory?->category?->is_consumable 
            ?? $item->subcategory?->category?->is_consumable 
            ?? false
        );

        if ($isConsumable) {
            return 0;
        }

        $existingCount = $item->fulfillments()->whereNotNull('unit_id')->count();
        $needed = (int) $item->quantity_requested - $existingCount;

        if ($needed <= 0) {
            return 0;
        }

        // Fetch candidate units excluding already assigned to this item
        $units = $this->getAvailableUnitsQuery($item, false)
            ->limit($needed)
            ->get();

        if ($units->isEmpty()) {
            return 0;
        }

        $createdCount = 0;
        DB::transaction(function () use ($units, $item, &$createdCount) {
            foreach ($units as $unit) {
                // Ensure no race condition assignment
                $alreadyTaken = RequestFulfillment::where('unit_id', $unit->id)
                    ->whereNull('completed_at')
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyTaken) {
                    continue;
                }

                RequestFulfillment::create([
                    'request_item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'lot_id' => $unit->lot_id,
                    'quantity_fulfilled' => 1,
                    'assigned_at' => now(),
                ]);

                $createdCount++;
            }
        });

        return $createdCount;
    }

    /**
     * Auto-fulfill a consumable item using FIFO on available LOTs.
     *
     * @param RequestItem $item
     * @return int Total quantity fulfilled in this execution
     */
    public function autoFulfillConsumableItem(RequestItem $item): int
    {
        $isConsumable = (bool) (
            $item->barang?->subcategory?->category?->is_consumable 
            ?? $item->subcategory?->category?->is_consumable 
            ?? false
        );

        if (!$isConsumable) {
            return 0;
        }

        $existingQuantity = (int) $item->fulfillments()
            ->whereNotNull('lot_id')
            ->whereNull('unit_id')
            ->sum('quantity_fulfilled');

        $needed = (int) $item->quantity_requested - $existingQuantity;

        if ($needed <= 0) {
            return 0;
        }

        $lotQuery = Lot::query()
            ->with(['barang.brand', 'location', 'floor', 'room'])
            ->where('current_quantity', '>', 0);

        if ($item->barang_id) {
            $lotQuery->where('barang_id', $item->barang_id);
        } else {
            $lotQuery->whereHas('barang', fn($bq) => $bq->where('subcategory_id', $item->subcategory_id));
        }

        $lots = $lotQuery->orderBy('date_of_receipt', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($lots->isEmpty()) {
            return 0;
        }

        $totalFulfilled = 0;
        DB::transaction(function () use ($lots, $item, $needed, &$totalFulfilled) {
            $remaining = $needed;

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                // Check existing fulfillment for this item and lot
                $existingFulfillment = RequestFulfillment::where('request_item_id', $item->id)
                    ->where('lot_id', $lot->id)
                    ->whereNull('unit_id')
                    ->first();

                $alreadyFulfilledFromThisLot = $existingFulfillment ? $existingFulfillment->quantity_fulfilled : 0;
                $availableInLot = max(0, $lot->current_quantity - $alreadyFulfilledFromThisLot);

                if ($availableInLot <= 0) {
                    continue;
                }

                $take = min($remaining, $availableInLot);

                if ($existingFulfillment) {
                    $existingFulfillment->update([
                        'quantity_fulfilled' => $existingFulfillment->quantity_fulfilled + $take,
                        'assigned_at' => now(),
                    ]);
                } else {
                    RequestFulfillment::create([
                        'request_item_id' => $item->id,
                        'unit_id' => null,
                        'lot_id' => $lot->id,
                        'quantity_fulfilled' => $take,
                        'assigned_at' => now(),
                    ]);
                }

                $remaining -= $take;
                $totalFulfilled += $take;
            }
        });

        return $totalFulfilled;
    }

    /**
     * Auto-fulfill all items in a request.
     *
     * @param SmartRequest $request
     * @return array<string, int>
     */
    public function autoFulfillRequest(SmartRequest $request): array
    {
        $request->loadMissing([
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.subcategory.category',
            'items.fulfillments.unit.lot',
            'items.fulfillments.lot.barang.brand',
        ]);

        $assetUnitsCount = 0;
        $consumableQtyCount = 0;

        foreach ($request->items as $item) {
            $isConsumable = (bool) (
                $item->barang?->subcategory?->category?->is_consumable 
                ?? $item->subcategory?->category?->is_consumable 
                ?? false
            );

            if ($isConsumable) {
                $consumableQtyCount += $this->autoFulfillConsumableItem($item);
            } else {
                $assetUnitsCount += $this->autoFulfillAssetItem($item);
            }
        }

        return [
            'asset_units' => $assetUnitsCount,
            'consumable_quantity' => $consumableQtyCount,
        ];
    }
}
