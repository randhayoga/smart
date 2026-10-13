<?php

namespace App\Models\Master;

use Database\Factories\Master\SubcategoryFactory;

/**
 * Legacy alias for Type model.
 * @deprecated Use App\Models\Master\Type instead.
 */
class Subcategory extends Type
{
    protected static function newFactory()
    {
        return SubcategoryFactory::new();
    }
}
