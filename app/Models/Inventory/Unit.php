<?php

namespace App\Models\Inventory;

use Database\Factories\Inventory\UnitFactory;

/**
 * Legacy alias for Asset.
 * @deprecated Use App\Models\Inventory\Asset instead.
 */
class Unit extends Asset
{
    protected static function newFactory()
    {
        return UnitFactory::new();
    }
}
