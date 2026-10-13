<?php

namespace App\Models\Inventory;

use Database\Factories\Inventory\AssetStatusApprovalFactory;

/**
 * Legacy alias for AssetStatusApproval.
 * @deprecated Use App\Models\Inventory\AssetStatusApproval instead.
 */
class UnitStatusApproval extends AssetStatusApproval
{
    protected static function newFactory()
    {
        return AssetStatusApprovalFactory::new();
    }
}
