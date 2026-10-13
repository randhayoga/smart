<?php

namespace App\Models\Inventory;

use Database\Factories\Inventory\AssetActivationApprovalFactory;

/**
 * Legacy alias for AssetActivationApproval.
 * @deprecated Use App\Models\Inventory\AssetActivationApproval instead.
 */
class UnitActivationApproval extends AssetActivationApproval
{
    protected static function newFactory()
    {
        return AssetActivationApprovalFactory::new();
    }
}
