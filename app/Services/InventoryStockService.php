<?php

namespace App\Services;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use Illuminate\Support\Facades\DB;

/**
 * Service providing high-performance batch and single stock availability calculations for inventory models and types.
 */
class InventoryStockService
{
    /**
     * Compute available stock for a specific model.
     */
    public function getAvailableStockForModel(int $modelId): int
    {
        $hasAnyAsset = Asset::whereHas('lot', fn($q) => $q->where('model_id', $modelId))->exists();

        if ($hasAnyAsset) {
            return (int) Asset::whereHas('lot', fn($q) => $q->where('model_id', $modelId))
                ->where('status', 'Tersedia')
                ->count();
        }

        return (int) Lot::where('model_id', $modelId)->sum('current_quantity');
    }

    /**
     * Alias for getAvailableStockForModel.
     */
    public function getAvailableStockForBarang(int $barangId): int
    {
        return $this->getAvailableStockForModel($barangId);
    }

    /**
     * Compute available stock for a non-specific type.
     */
    public function getAvailableStockForType(int $typeId): int
    {
        $hasAnyAsset = Asset::whereHas('lot.model', fn($q) => $q->where('type_id', $typeId))->exists();

        if ($hasAnyAsset) {
            return (int) Asset::whereHas('lot.model', fn($q) => $q->where('type_id', $typeId))
                ->where('status', 'Tersedia')
                ->count();
        }

        return (int) Lot::whereHas('model', fn($q) => $q->where('type_id', $typeId))->sum('current_quantity');
    }

    /**
     * Alias for getAvailableStockForType.
     */
    public function getAvailableStockForSubcategory(int $subcategoryId): int
    {
        return $this->getAvailableStockForType($subcategoryId);
    }

    /**
     * Compute available stock in batch for multiple models/types.
     *
     * @param iterable $items Collection of RequestItem or array of objects/arrays with model_id and type_id
     * @return array<string, int> Associative map of 'model_{id}' => stock and 'type_{id}' => stock
     */
    public function getBatchAvailableStock(iterable $items): array
    {
        $modelIds = [];
        $typeIds = [];

        foreach ($items as $item) {
            $mId = is_array($item)
                ? ($item['model_id'] ?? $item['barang_id'] ?? null)
                : ($item->model_id ?? $item->barang_id ?? null);
            $tId = is_array($item)
                ? ($item['type_id'] ?? $item['subcategory_id'] ?? null)
                : ($item->type_id ?? $item->subcategory_id ?? null);

            if ($mId) {
                $modelIds[(int) $mId] = (int) $mId;
            } elseif ($tId) {
                $typeIds[(int) $tId] = (int) $tId;
            }
        }

        $stockMap = [];

        // 1. Process specific models
        if (!empty($modelIds)) {
            // Find which models track serialized assets
            $modelsWithAssets = DB::table('assets')
                ->join('lots', 'assets.lot_id', '=', 'lots.id')
                ->whereIn('lots.model_id', array_values($modelIds))
                ->distinct()
                ->pluck('lots.model_id')
                ->map(fn($id) => (int) $id)
                ->all();

            // Asset-tracked available count
            if (!empty($modelsWithAssets)) {
                $assetCounts = DB::table('assets')
                    ->join('lots', 'assets.lot_id', '=', 'lots.id')
                    ->whereIn('lots.model_id', $modelsWithAssets)
                    ->where('assets.status', 'Tersedia')
                    ->groupBy('lots.model_id')
                    ->select('lots.model_id', DB::raw('count(assets.id) as total'))
                    ->pluck('total', 'lots.model_id');

                foreach ($modelsWithAssets as $mId) {
                    $val = (int) ($assetCounts[$mId] ?? 0);
                    $stockMap["model_{$mId}"] = $val;
                    $stockMap["barang_{$mId}"] = $val;
                }
            }

            // Consumable models without assets (lot-tracked current_quantity sum)
            $consumableModelIds = array_diff(array_values($modelIds), $modelsWithAssets);
            if (!empty($consumableModelIds)) {
                $consumableSums = DB::table('lots')
                    ->whereIn('model_id', $consumableModelIds)
                    ->groupBy('model_id')
                    ->select('model_id', DB::raw('sum(current_quantity) as total'))
                    ->pluck('total', 'model_id');

                foreach ($consumableModelIds as $mId) {
                    $val = (int) ($consumableSums[$mId] ?? 0);
                    $stockMap["model_{$mId}"] = $val;
                    $stockMap["barang_{$mId}"] = $val;
                }
            }
        }

        // 2. Process non-specific types
        if (!empty($typeIds)) {
            // Find which types have any serialized assets
            $typesWithAssets = DB::table('assets')
                ->join('lots', 'assets.lot_id', '=', 'lots.id')
                ->join('models', 'lots.model_id', '=', 'models.id')
                ->whereIn('models.type_id', array_values($typeIds))
                ->distinct()
                ->pluck('models.type_id')
                ->map(fn($id) => (int) $id)
                ->all();

            if (!empty($typesWithAssets)) {
                $typeAssetCounts = DB::table('assets')
                    ->join('lots', 'assets.lot_id', '=', 'lots.id')
                    ->join('models', 'lots.model_id', '=', 'models.id')
                    ->whereIn('models.type_id', $typesWithAssets)
                    ->where('assets.status', 'Tersedia')
                    ->groupBy('models.type_id')
                    ->select('models.type_id', DB::raw('count(assets.id) as total'))
                    ->pluck('total', 'models.type_id');

                foreach ($typesWithAssets as $tId) {
                    $val = (int) ($typeAssetCounts[$tId] ?? 0);
                    $stockMap["type_{$tId}"] = $val;
                    $stockMap["subcat_{$tId}"] = $val;
                }
            }

            $consumableTypeIds = array_diff(array_values($typeIds), $typesWithAssets);
            if (!empty($consumableTypeIds)) {
                $typeConsumableSums = DB::table('lots')
                    ->join('models', 'lots.model_id', '=', 'models.id')
                    ->whereIn('models.type_id', $consumableTypeIds)
                    ->groupBy('models.type_id')
                    ->select('models.type_id', DB::raw('sum(lots.current_quantity) as total'))
                    ->pluck('total', 'models.type_id');

                foreach ($consumableTypeIds as $tId) {
                    $val = (int) ($typeConsumableSums[$tId] ?? 0);
                    $stockMap["type_{$tId}"] = $val;
                    $stockMap["subcat_{$tId}"] = $val;
                }
            }
        }

        return $stockMap;
    }

    /**
     * Resolve key helper.
     */
    public static function itemKey(?int $modelId, ?int $typeId): string
    {
        return $modelId ? "model_{$modelId}" : "type_{$typeId}";
    }
}
