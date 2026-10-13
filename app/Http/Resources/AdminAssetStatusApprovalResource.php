<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin Asset Status Approval Resource formatting asset condition proposals and review decisions.
 *
 * @mixin \App\Models\Inventory\AssetStatusApproval
 */
class AdminAssetStatusApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $asset = $this->asset ?? $this->unit;
        $assetId = $this->asset_id ?? $this->unit_id;

        return [
            'id' => $this->id,
            'asset_id' => $assetId,
            'unit_id' => $assetId,
            'asset_number' => $asset->number ?? '-',
            'unit_number' => $asset->number ?? '-',
            'proposed_condition' => $this->proposed_condition,
            'previous_condition' => $this->previous_condition,
            'proposed_status' => $this->proposed_condition,
            'previous_status' => $this->previous_status,
            'decision' => $this->decision,
            'note' => $this->note,
            'requester_name' => $this->requester->name ?? '-',
            'approver_name' => $this->approver->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d-m-Y H:i') : '-',
        ];
    }
}
