<?php

namespace App\Models\Inventory;

/**
 * Legacy alias for AssetLifecycle.
 * @deprecated Use App\Models\Inventory\AssetLifecycle instead.
 */
class UnitLifecycle extends AssetLifecycle
{
    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if (is_string($column) && ($column === 'unit_id' || $column === 'asset_lifecycles.unit_id')) {
                    $column = str_replace('unit_id', 'asset_id', $column);
                }
                return parent::where($column, $operator, $value, $boolean);
            }
        };
    }
}
