<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Inventory\AssetLifecycle;

/**
 * Manager Asset Deletion Approval Resource transforming asset deletion and disposal requests with lifecycle trails.
 *
 * @mixin \App\Models\Inventory\AssetDeletionApproval
 */
class ManagerAssetDeletionApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $asset = $this->asset;
        $lot = $asset?->lot;
        $model = $lot?->model;

        $assetId = $this->asset_id;

        // Use eager-loaded lifecycles if available to avoid N+1 queries
        $rawLifecycles = ($asset && $asset->relationLoaded('lifecycles'))
            ? $asset->lifecycles->sortByDesc('id')->values()
            : AssetLifecycle::with(['actor'])
                ->where('asset_id', $assetId)
                ->orderBy('id', 'desc')
                ->get();

        $lifecycles = $rawLifecycles->map(function ($lc) {
            $duration = $lc->formatted_duration;

            $actorRole = 'System';
            $actorName = 'System';
            if ($lc->actor) {
                $actorName = $lc->actor->name ?? '-';
                if ($lc->actor->role === 'admin') {
                    $actorRole = 'Admin';
                } else if (in_array($lc->actor->role, ['manager', 'ifs_manager'])) {
                    $actorRole = 'Manager';
                } else {
                    $actorRole = 'User';
                }
            }

            return [
                'waktu' => $lc->start_date ? $lc->start_date->format('d-m-Y H:i:s') : '-',
                'status' => $lc->status ?? '-',
                'action_type' => $lc->action_type ?? '-',
                'aktor' => "{$actorRole}: {$actorName}",
                'durasi' => $duration,
                'catatan' => $lc->note ?? '-',
            ];
        });

        $catName = $model?->type?->category?->name ?? '-';
        $typeName = $model?->type?->name ?? '-';
        $brandName = $model?->brand?->name ?? '-';
        $modelName = $model?->name ?? '-';
        $specName = $model?->specification ?? '-';

        $assetDetails = [
            'id' => $asset?->id,
            'number' => $asset?->number ?? '-',
            'legacy_number' => $asset?->legacy_number ?? null,
            'status' => $asset?->status ?? '-',
            'approval_status' => $asset?->approval_status ?? null,
            'condition' => $asset?->condition ?? '-',
            'classification' => $asset?->classification ?? 'Aset',
            'type' => $asset?->borrowing_type ?? $typeName,
            'price' => $asset?->price ? number_format($asset->price, 0, ',', '.') : '-',
            'image_url' => $asset?->image_url ? '/media/' . $asset->image_url : ($lot?->image_url ? '/media/' . $lot->image_url : null),
            'vehicle_registration' => $asset?->vehicle_registration ?? '-',
            'location' => $asset?->location ? $asset->location->full_name : '-',

            'lot_number' => $lot?->number ?? '-',
            'lot_code' => $lot?->number ?? '-',
            'organizer' => $lot?->organizer->name ?? '-',
            'purchase_grant_date' => $lot?->purchase_grant_date ? $lot->purchase_grant_date->format('d-m-Y') : '-',
            'age' => $lot ? $lot->age : null,
            'vendor' => $lot?->vendor_name ?? '-',
            'po_number' => $lot?->po_number ?? '-',
            'model_code' => $model?->number ?? '-',
            'model_number' => $model?->number ?? '-',
            'model_name' => $modelName,
            'model_spec' => $specName,
            'name' => $modelName,
            'nama' => $modelName,
            'brand' => $brandName,
            'category' => $catName,
            'subcategory' => $typeName,
            'specification' => $specName,
            'uom' => $model?->uom?->name ?? '-',
            'lifecycles' => $lifecycles,
        ];

        return [
            'id' => $this->id,
            'asset_id' => $assetId,
            'unit_id' => $assetId,
            'asset_code' => $asset->number ?? '-',
            'asset_number' => $asset->number ?? '-',
            'category' => $catName,
            'type' => $typeName,
            'subcategory' => $typeName,
            'brand' => $brandName,
            'name' => $modelName,
            'nama' => $modelName,
            'specification' => $specName,
            'proposed_condition' => $this->proposed_condition,
            'previous_condition' => $this->previous_condition,
            'proposed_status' => $this->proposed_condition,
            'previous_status' => $this->previous_status,
            'status_label' => $this->proposed_condition ?? '-',
            'decision' => $this->decision,
            'classification' => $asset?->classification ?? 'Aset',
            'admin_note' => $this->admin_note,
            'dm_note' => $this->dm_note,
            'note' => $this->dm_note,
            'requested_by' => $this->requester->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d-m-Y H:i') : null,
            'approver_name' => $this->approver->name ?? null,
            'memo_url' => $this->memo_url,
            'lost_doc_url' => $this->lost_doc_url,
            'bod_boc_approval_url' => $this->bod_boc_approval_url,
            'bod_boc_disapproval_url' => $this->bod_boc_disapproval_url,
            'asset_details' => $assetDetails,
            'unit_details' => $assetDetails,
        ];
    }
}
