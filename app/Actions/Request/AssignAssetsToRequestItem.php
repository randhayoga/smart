<?php

namespace App\Actions\Request;

use App\Models\Inventory\Asset;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to manually assign or update asset allocations for a request item.
 * Preserves already handed-over assets and guards against over-allocation or concurrent claims.
 */
class AssignAssetsToRequestItem
{
    /**
     * Execute asset assignment for an item.
     *
     * @param RequestItem $item
     * @param array<int|string> $assetIds Array of selected asset IDs or asset numbers
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws ValidationException
     */
    public function execute(RequestItem $item, array $assetIds)
    {
        $item->loadMissing(['request', 'model', 'type']);

        $quantityRequested = (int) $item->quantity_requested;
        $uniqueAssetIds = array_values(array_unique(array_filter($assetIds)));

        if (count($uniqueAssetIds) > $quantityRequested) {
            throw ValidationException::withMessages([
                'asset_ids' => ["Maksimal {$quantityRequested} aset yang dapat dialokasikan."],
            ]);
        }

        return DB::transaction(function () use ($item, $uniqueAssetIds, $quantityRequested) {
            // 1. Identify already handed-over or locked fulfillments
            $lockedFulfillments = RequestFulfillment::where('request_item_id', $item->id)
                ->where(function ($q) {
                    $q->whereNotNull('handover_id')
                      ->orWhereNotNull('assigned_at')
                      ->orWhereNotNull('confirmed_at')
                      ->orWhereNotNull('completed_at')
                      ->orWhereHas('asset', fn($uq) => $uq->whereIn('status', ['Dipinjam (ST)', 'Di-assign (LT)', 'Dipinjam (sewa)']));
                })
                ->get();

            $lockedAssetIds = $lockedFulfillments->pluck('asset_id')->filter()->all();

            // 2. Newly requested asset IDs must include locked assets, or not displace them
            $missingLocked = array_diff($lockedAssetIds, $uniqueAssetIds);
            if (!empty($missingLocked)) {
                $uniqueAssetIds = array_values(array_unique(array_merge($uniqueAssetIds, $lockedAssetIds)));
            }

            if (count($uniqueAssetIds) > $quantityRequested) {
                throw ValidationException::withMessages([
                    'asset_ids' => ["Total alokasi melebihi batas permintaan ({$quantityRequested} aset)."],
                ]);
            }

            // 3. Assets to assign that are not already locked
            $newAssetIds = array_diff($uniqueAssetIds, $lockedAssetIds);

            if (!empty($newAssetIds)) {
                // Validate asset existence and suitability
                $assets = Asset::with('lot.model')->whereIn('id', $newAssetIds)->get();

                if ($assets->count() !== count($newAssetIds)) {
                    throw ValidationException::withMessages([
                        'asset_ids' => ['Satu atau lebih aset tidak ditemukan.'],
                    ]);
                }

                foreach ($assets as $asset) {
                    // Check availability status
                    if (strtolower((string)$asset->status) !== 'tersedia') {
                        throw ValidationException::withMessages([
                            'asset_ids' => ["Aset {$asset->number} saat ini berstatus '{$asset->status}' dan tidak dapat dialokasikan."],
                        ]);
                    }

                    // Check that asset belongs to the item's catalog
                    $lotModelId = $asset->lot?->model_id;
                    $itemModelId = $item->model_id ?? $item->barang_id;
                    $itemTypeId = $item->type_id ?? $item->subcategory_id ?? $item->model?->type_id;
                    $lotTypeId = $asset->lot?->model?->type_id;

                    $matches = false;
                    if ($itemModelId && (int)$lotModelId === (int)$itemModelId) {
                        $matches = true;
                    } elseif ($itemTypeId && (int)$lotTypeId === (int)$itemTypeId) {
                        $matches = true;
                    }

                    if (!$matches) {
                        throw ValidationException::withMessages([
                            'asset_ids' => ["Aset {$asset->number} tidak cocok dengan model yang diminta."],
                        ]);
                    }

                    // Check that asset is not locked by another confirmed active request
                    $isAssignedElsewhere = RequestFulfillment::where('asset_id', $asset->id)
                        ->where('request_item_id', '!=', $item->id)
                        ->where(function ($q) {
                            $q->whereNotNull('assigned_at')
                              ->orWhereNotNull('confirmed_at')
                              ->orWhereNotNull('completed_at')
                              ->orWhereNotNull('handover_id');
                        })
                        ->lockForUpdate()
                        ->exists();

                    if ($isAssignedElsewhere) {
                        throw ValidationException::withMessages([
                            'asset_ids' => ["Aset {$asset->number} telah dikonfirmasi pada permintaan lain."],
                        ]);
                    }
                }
            }

            // 4. Remove existing pending (unlocked) fulfillments for this item that are not in uniqueAssetIds
            RequestFulfillment::where('request_item_id', $item->id)
                ->whereNotIn('id', $lockedFulfillments->pluck('id'))
                ->whereNotIn('asset_id', $uniqueAssetIds)
                ->delete();

            // 5. Create fulfillments for new assets
            $existingAssetIdsInItem = RequestFulfillment::where('request_item_id', $item->id)
                ->pluck('asset_id')
                ->filter()
                ->all();

            foreach ($newAssetIds as $aid) {
                if (!in_array($aid, $existingAssetIdsInItem)) {
                    $assetModel = Asset::find($aid);
                    RequestFulfillment::create([
                        'request_item_id' => $item->id,
                        'asset_id' => $aid,
                        'lot_id' => $assetModel?->lot_id,
                        'quantity_fulfilled' => 1,
                        'assigned_at' => null,
                    ]);
                }
            }

            return RequestFulfillment::where('request_item_id', $item->id)->with('asset.lot')->get();
        });
    }
}
