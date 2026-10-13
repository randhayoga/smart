<?php

namespace App\Models\Inventory;

use Database\Factories\Inventory\BarangFactory;

/**
 * Legacy alias for Model.
 * @deprecated Use App\Models\Inventory\Model instead.
 */
class Barang extends Model
{
    protected static function newFactory()
    {
        return BarangFactory::new();
    }
}
