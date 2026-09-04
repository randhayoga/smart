<?php

namespace App\Services;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Service providing high-performance batch and single stock availability calculations for inventory items and subcategories.
 */
class InventoryStockService
{
    /**
     * Compute available stock for a specific barang.
     */
    public function getAvailableStockForBarang(int $barangId): int
    {
        $hasAnyUnit = Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))->exists();

        if ($hasAnyUnit) {
            return (int) Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))
                ->where('status', 'Tersedia')
                ->count();
        }

        return (int) Lot::where('barang_id', $barangId)->sum('current_quantity');
    }

    /**
     * Compute available stock for a non-specific subcategory.
     */
    public function getAvailableStockForSubcategory(int $subcategoryId): int
    {
        $hasAnyUnit = Unit::whereHas('lot.barang', fn($q) => $q->where('subcategory_id', $subcategoryId))->exists();

        if ($hasAnyUnit) {
            return (int) Unit::whereHas('lot.barang', fn($q) => $q->where('subcategory_id', $subcategoryId))
                ->where('status', 'Tersedia')
                ->count();
        }

        return (int) Lot::whereHas('barang', fn($q) => $q->where('subcategory_id', $subcategoryId))->sum('current_quantity');
    }

    /**
     * Compute available stock in batch for multiple items/subcategories.
     * Replaces 2*N queries with 2-4 grouped queries.
     *
     * @param iterable $items Collection of RequestItem or array of objects/arrays with barang_id and subcategory_id
     * @return array<string, int> Associative map of 'barang_{id}' => stock and 'subcat_{id}' => stock
     */
    public function getBatchAvailableStock(iterable $items): array
    {
        $barangIds = [];
        $subcategoryIds = [];

        foreach ($items as $item) {
            $bId = is_array($item) ? ($item['barang_id'] ?? null) : ($item->barang_id ?? null);
            $sId = is_array($item) ? ($item['subcategory_id'] ?? null) : ($item->subcategory_id ?? null);

            if ($bId) {
                $barangIds[(int) $bId] = (int) $bId;
            } elseif ($sId) {
                $subcategoryIds[(int) $sId] = (int) $sId;
            }
        }

        $stockMap = [];

        // 1. Process specific barangs
        if (!empty($barangIds)) {
            // Find which barangs track serialized units
            $barangsWithUnits = DB::table('units')
                ->join('lots', 'units.lot_id', '=', 'lots.id')
                ->whereIn('lots.barang_id', array_values($barangIds))
                ->distinct()
                ->pluck('lots.barang_id')
                ->map(fn($id) => (int) $id)
                ->all();

            // Unit-tracked available count
            if (!empty($barangsWithUnits)) {
                $unitCounts = DB::table('units')
                    ->join('lots', 'units.lot_id', '=', 'lots.id')
                    ->whereIn('lots.barang_id', $barangsWithUnits)
                    ->where('units.status', 'Tersedia')
                    ->groupBy('lots.barang_id')
                    ->select('lots.barang_id', DB::raw('count(units.id) as total'))
                    ->pluck('total', 'lots.barang_id');

                foreach ($barangsWithUnits as $bId) {
                    $stockMap["barang_{$bId}"] = (int) ($unitCounts[$bId] ?? 0);
                }
            }

            // Consumable barangs without units (lot-tracked current_quantity sum)
            $consumableBarangIds = array_diff(array_values($barangIds), $barangsWithUnits);
            if (!empty($consumableBarangIds)) {
                $consumableSums = DB::table('lots')
                    ->whereIn('barang_id', $consumableBarangIds)
                    ->groupBy('barang_id')
                    ->select('barang_id', DB::raw('sum(current_quantity) as total'))
                    ->pluck('total', 'barang_id');

                foreach ($consumableBarangIds as $bId) {
                    $stockMap["barang_{$bId}"] = (int) ($consumableSums[$bId] ?? 0);
                }
            }
        }

        // 2. Process non-specific subcategories
        if (!empty($subcategoryIds)) {
            // Find which subcategories have any serialized units
            $subcatsWithUnits = DB::table('units')
                ->join('lots', 'units.lot_id', '=', 'lots.id')
                ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
                ->whereIn('barangs.subcategory_id', array_values($subcategoryIds))
                ->distinct()
                ->pluck('barangs.subcategory_id')
                ->map(fn($id) => (int) $id)
                ->all();

            if (!empty($subcatsWithUnits)) {
                $subcatUnitCounts = DB::table('units')
                    ->join('lots', 'units.lot_id', '=', 'lots.id')
                    ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
                    ->whereIn('barangs.subcategory_id', $subcatsWithUnits)
                    ->where('units.status', 'Tersedia')
                    ->groupBy('barangs.subcategory_id')
                    ->select('barangs.subcategory_id', DB::raw('count(units.id) as total'))
                    ->pluck('total', 'barangs.subcategory_id');

                foreach ($subcatsWithUnits as $sId) {
                    $stockMap["subcat_{$sId}"] = (int) ($subcatUnitCounts[$sId] ?? 0);
                }
            }

            $consumableSubcatIds = array_diff(array_values($subcategoryIds), $subcatsWithUnits);
            if (!empty($consumableSubcatIds)) {
                $subcatConsumableSums = DB::table('lots')
                    ->join('barangs', 'lots.barang_id', '=', 'barangs.id')
                    ->whereIn('barangs.subcategory_id', $consumableSubcatIds)
                    ->groupBy('barangs.subcategory_id')
                    ->select('barangs.subcategory_id', DB::raw('sum(lots.current_quantity) as total'))
                    ->pluck('total', 'barangs.subcategory_id');

                foreach ($consumableSubcatIds as $sId) {
                    $stockMap["subcat_{$sId}"] = (int) ($subcatConsumableSums[$sId] ?? 0);
                }
            }
        }

        return $stockMap;
    }

    /**
     * Resolve key helper.
     */
    public static function itemKey(?int $barangId, ?int $subcategoryId): string
    {
        return $barangId ? "barang_{$barangId}" : "subcat_{$subcategoryId}";
    }
}
