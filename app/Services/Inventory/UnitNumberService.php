<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Lot;

/**
 * Legacy alias for AssetNumberService.
 * @deprecated Use App\Services\Inventory\AssetNumberService instead.
 */
class UnitNumberService extends AssetNumberService
{
    public function generateUnitNumber(Lot $lot, ?int $serial = null): string
    {
        return $this->generateAssetNumber($lot, $serial);
    }

    public function generateBulkUnitNumbers(Lot $lot, int $quantity, ?string $fallbackBaseNumber = null): array
    {
        return $this->generateBulkAssetNumbers($lot, $quantity, $fallbackBaseNumber);
    }
}
