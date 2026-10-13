<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Inventory\AssetLifecycle;

/**
 * Manager Asset Activation Approval Resource transforming asset unit registration approval requests with lifecycle trails.
 *
 * @mixin \App\Models\Inventory\AssetActivationApproval
 */
class ManagerAssetActivationApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $asset = $this->asset ?? $this->unit;
        $lot = $asset?->lot;
        $model = $lot?->model ?? $lot?->barang;

        $assetId = $this->asset_id ?? $this->unit_id;

        // Fetch lifecycles for audit trail
        $lifecycles = AssetLifecycle::with(['actor'])
            ->where('asset_id', $assetId)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($lc) {
                $duration = $lc->formatted_duration;

                $actorRole = 'System';
                $actorName = 'System';
                if ($lc->actor) {
                    $actorName = $lc->actor->name ?? '-';
                    if ($lc->actor->role === 'admin') {
                        $actorRole = 'Admin';
                    } elseif (in_array($lc->actor->role, ['manager', 'ifs_manager'])) {
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

        $catName = $model?->type?->category?->name ?? $model?->subcategory?->category?->name ?? '-';
        $typeName = $model?->type?->name ?? $model?->subcategory?->name ?? '-';

        return [
            'id' => $this->id,
            'asset_id' => $assetId,
            'unit_id' => $assetId,
            'asset_code' => $asset->number ?? '-',
            'asset_number' => $asset->number ?? '-',
            'category' => $catName,
            'type' => $typeName,
            'subcategory' => $typeName,
            'brand' => $model?->brand?->name ?? '-',
            'name' => $model?->name ?? '-',
            'nama' => $model?->name ?? '-',
            'specification' => $model?->specification ?? '-',
            'decision' => $this->decision,
            'note' => $this->note,
            'requested_by' => $this->requester->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d-m-Y H:i') : null,
            'approver_name' => $this->approver->name ?? null,
            'asset_details' => [
                'id' => $asset?->id,
                'number' => $asset?->number ?? '-',
                'legacy_number' => $asset?->legacy_number ?? null,
                'status' => $asset?->status ?? '-',
                'condition' => $asset?->condition ?? '-',
                'price' => $asset?->price ? number_format($asset->price, 0, ',', '.') : '-',
                'image_url' => $asset?->image_url ? '/media/' . $asset->image_url : ($lot?->image_url ? '/media/' . $lot->image_url : null),
                'vehicle_registration' => $asset?->vehicle_registration ?? '-',
                'location' => $asset?->location ? $asset->location->full_name : '-',

                'lot_number' => $lot?->number ?? '-',
                'lot_code' => $lot?->number ?? '-',
                'organizer' => $lot?->organizer->name ?? '-',
                'date_of_receipt' => $lot?->date_of_receipt ? $lot->date_of_receipt->format('d-m-Y') : '-',
                'age' => $lot ? $lot->age : null,
                'vendor' => $lot?->vendor_name ?? '-',
                'po_number' => $lot?->po_number ?? '-',
                'model_code' => $model?->number ?? '-',
                'model_name' => $model?->name ?? '-',
                'model_spec' => $model?->specification ?? '-',
                'model_unit' => $model?->uom?->name ?? 'pcs',
                'barang_code' => $model?->number ?? '-',
                'barang_nama' => $model?->name ?? '-',
                'barang_spec' => $model?->specification ?? '-',
                'barang_unit' => $model?->uom?->name ?? 'pcs',

                'lifecycles' => $lifecycles,
            ],
            'unit_details' => [
                'id' => $asset?->id,
                'number' => $asset?->number ?? '-',
                'legacy_number' => $asset?->legacy_number ?? null,
                'status' => $asset?->status ?? '-',
                'condition' => $asset?->condition ?? '-',
                'price' => $asset?->price ? number_format($asset->price, 0, ',', '.') : '-',
                'image_url' => $asset?->image_url ? '/media/' . $asset->image_url : ($lot?->image_url ? '/media/' . $lot->image_url : null),
                'vehicle_registration' => $asset?->vehicle_registration ?? '-',
                'location' => $asset?->location ? $asset->location->full_name : '-',

                'lot_code' => $lot?->number ?? '-',
                'organizer' => $lot?->organizer->name ?? '-',
                'date_of_receipt' => $lot?->date_of_receipt ? $lot->date_of_receipt->format('d-m-Y') : '-',
                'age' => $lot ? $lot->age : null,
                'vendor' => $lot?->vendor_name ?? '-',
                'po_number' => $lot?->po_number ?? '-',
                'barang_code' => $model?->number ?? '-',
                'barang_nama' => $model?->name ?? '-',
                'barang_spec' => $model?->specification ?? '-',
                'barang_unit' => $model?->uom?->name ?? 'pcs',

                'lifecycles' => $lifecycles,
            ],
        ];
    }
}
