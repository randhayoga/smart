<?php

namespace App\Services;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Request Fulfillment Service managing automatic FIFO fulfillment and asset availability queries.
 */
class RequestFulfillmentService
{
    /**
     * Query available assets for a given request item.
     *
     * @param RequestItem $item
     * @param bool $includeCurrentItemAssignments Whether to include assets already assigned to this item
     * @return Builder
     */
    public function getAvailableAssetsQuery(RequestItem $item, bool $includeCurrentItemAssignments = false): Builder
    {
        $query = Asset::query()
            ->with(['lot.model.brand', 'location.parent'])
            ->whereRaw("LOWER(assets.status) = 'tersedia'");

        // Filter by item model or type
        $query->whereHas('lot', function ($lq) use ($item) {
            $mId = $item->model_id ?? $item->barang_id;
            $tId = $item->type_id ?? $item->subcategory_id;

            if ($mId) {
                $lq->where('model_id', $mId);
            } else {
                $lq->whereHas('model', fn($mq) => $mq->where('type_id', $tId));
            }
        });

        // Ensure asset is not locked by a confirmed fulfillment or already handed over
        $query->whereDoesntHave('fulfillments', function ($fq) use ($item, $includeCurrentItemAssignments) {
            $fq->where(function ($q) {
                $q->whereNotNull('assigned_at')
                  ->orWhereNotNull('confirmed_at')
                  ->orWhereNotNull('completed_at')
                  ->orWhereNotNull('handover_id');
            });
            if ($includeCurrentItemAssignments) {
                $fq->where('request_item_id', '!=', $item->id);
            }
        });

        // FIFO ordering: oldest lot receipt date first, then creation timestamp
        $query->leftJoin('lots', 'assets.lot_id', '=', 'lots.id')
            ->select('assets.*')
            ->orderBy('lots.purchase_grant_date', 'asc')
            ->orderBy('assets.created_at', 'asc')
            ->orderBy('assets.id', 'asc');

        return $query;
    }

    /**
     * Alias for getAvailableAssetsQuery.
     */
    public function getAvailableUnitsQuery(RequestItem $item, bool $includeCurrentItemAssignments = false): Builder
    {
        return $this->getAvailableAssetsQuery($item, $includeCurrentItemAssignments);
    }

    /**
     * Query available LOTs for a given consumable request item.
     *
     * @param RequestItem $item
     * @return Builder
     */
    public function getAvailableLotsQuery(RequestItem $item): Builder
    {
        $mId = $item->model_id ?? $item->barang_id;
        $tId = $item->type_id ?? $item->subcategory_id;

        $query = Lot::query()
            ->with(['model.brand', 'model.uom', 'location.parent'])
            ->where(function ($q) use ($item) {
                $q->where('current_quantity', '>', 0)
                  ->orWhereHas('fulfillments', fn($fq) => $fq->where('request_item_id', $item->id));
            });

        if ($mId) {
            $query->where('model_id', $mId);
        } else {
            $query->whereHas('model', fn($mq) => $mq->where('type_id', $tId));
        }

        return $query->orderBy('purchase_grant_date', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Auto-fulfill an asset (non-consumable) item using FIFO.
     *
     * @param RequestItem $item
     * @return int Count of newly fulfilled assets
     */
    public function autoFulfillAssetItem(RequestItem $item): int
    {
        $isConsumable = (bool) $item->is_consumable;

        if ($isConsumable) {
            return 0;
        }

        $existingCount = $item->fulfillments()->whereNotNull('asset_id')->count();
        $needed = (int) $item->quantity_requested - $existingCount;

        if ($needed <= 0) {
            return 0;
        }

        // Fetch candidate assets excluding already assigned to this item
        $assets = $this->getAvailableAssetsQuery($item, false)
            ->limit($needed)
            ->get();

        if ($assets->isEmpty()) {
            return 0;
        }

        $createdCount = 0;
        DB::transaction(function () use ($assets, $item, &$createdCount) {
            foreach ($assets as $asset) {
                // Ensure asset is not locked by a confirmed fulfillment
                $alreadyTaken = RequestFulfillment::where('asset_id', $asset->id)
                    ->where(function ($q) {
                        $q->whereNotNull('assigned_at')
                          ->orWhereNotNull('confirmed_at')
                          ->orWhereNotNull('completed_at')
                          ->orWhereNotNull('handover_id');
                    })
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyTaken) {
                    continue;
                }

                RequestFulfillment::create([
                    'request_item_id' => $item->id,
                    'asset_id' => $asset->id,
                    'lot_id' => $asset->lot_id,
                    'quantity_fulfilled' => 1,
                    'assigned_at' => null,
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
        $isConsumable = (bool) $item->is_consumable;

        if (!$isConsumable) {
            return 0;
        }

        $existingQuantity = (int) $item->fulfillments()
            ->whereNotNull('lot_id')
            ->whereNull('asset_id')
            ->sum('quantity_fulfilled');

        $needed = (int) $item->quantity_requested - $existingQuantity;

        if ($needed <= 0) {
            return 0;
        }

        $mId = $item->model_id ?? $item->barang_id;
        $tId = $item->type_id ?? $item->subcategory_id;

        $lotQuery = Lot::query()
            ->with(['model.brand', 'location.parent'])
            ->where('current_quantity', '>', 0);

        if ($mId) {
            $lotQuery->where('model_id', $mId);
        } else {
            $lotQuery->whereHas('model', fn($mq) => $mq->where('type_id', $tId));
        }

        $lots = $lotQuery->orderBy('purchase_grant_date', 'asc')
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
                    ->whereNull('asset_id')
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
                        'assigned_at' => null,
                    ]);
                } else {
                    RequestFulfillment::create([
                        'request_item_id' => $item->id,
                        'asset_id' => null,
                        'lot_id' => $lot->id,
                        'quantity_fulfilled' => $take,
                        'assigned_at' => null,
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
            'items.model.type.category',
            'items.model.brand',
            'items.type.category',
            'items.fulfillments.asset.lot',
            'items.fulfillments.lot.model.brand',
        ]);

        $assetUnitsCount = 0;
        $consumableQtyCount = 0;

        foreach ($request->items as $item) {
            $isConsumable = (bool) $item->is_consumable;

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
