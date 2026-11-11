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
                    if (in_array($lc->actor->role, ['admin_cfs', 'admin_ict'], true)) {
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
                    'condition' => $lc->condition ?? '-',
                    'action_type' => $lc->action_type ?? '-',
                    'aktor' => $actorName,
                    'durasi' => $duration,
                    'catatan' => $lc->note ?? '-',
                ];
            });

        $catName = $model?->type?->category?->name ?? $model?->subcategory?->category?->name ?? '-';
        $catCode = $model?->type?->category?->code ?? $model?->subcategory?->category?->code ?? null;
        $typeName = $model?->type?->name ?? $model?->subcategory?->name ?? '-';

        $isComp = ($catCode === 'COMP') || ($catName === 'Computer') || ($catName === 'Komputer');
        $compSpec = $asset?->comp_specification;
        $effectiveSpec = ($isComp && $compSpec !== null && $compSpec !== '')
            ? $compSpec
            : ($model?->specification ?? '-');

        $details = [
            'id' => $asset?->id,
            'number' => $asset?->number ?? '-',
            'legacy_number' => $asset?->legacy_number ?? null,
            'status' => $asset?->status ?? '-',
            'approval_status' => $asset?->approval_status ?? ($this->decision === 'approved' ? null : 'Pending:DM'),
            'condition' => $asset?->condition,
            'qc_pass' => $asset?->qc_pass,
            'last_qc' => $asset?->last_qc?->format('Y-m-d H:i:s'),
            'last_qc_formatted' => $asset?->last_qc?->format('d-m-Y'),
            'license_exp_date' => $asset?->license_exp_date?->format('Y-m-d'),
            'renewal_reminder' => $asset?->renewal_reminder,
            'category_code' => $catCode,
            'model_category_code' => $catCode,
            'classification' => $asset?->classification ?? 'Aset',
            'borrowing_type' => $asset?->borrowing_type,
            'type' => $asset?->borrowing_type ?? $typeName,
            'organizer' => $asset?->organizer ?? ($lot?->organizer->name ?? '-'),
            'burden' => $asset?->burden,
            'project_no' => $asset?->project?->no_project,
            'project_name' => $asset?->project?->project_name,
            'updated_at' => $asset?->updated_at ? $asset->updated_at->format('d M Y, H:i') : null,
            'floor' => $asset?->floor,
            'room' => $asset?->room,
            'price' => $asset?->price ? number_format($asset->price, 0, ',', '.') : '-',
            'image_url' => $asset?->image_url ? '/media/' . $asset->image_url : ($lot?->image_url ? '/media/' . $lot->image_url : null),
            'vehicle_registration' => $asset?->vehicle_registration ?? '-',
            'location' => $asset?->location ? $asset->location->full_name : '-',

            'lot_number' => $lot?->number ?? '-',
            'lot_code' => $lot?->number ?? '-',
            'purchase_grant_date' => $lot?->purchase_grant_date ? $lot->purchase_grant_date->format('d-m-Y') : '-',
            'age' => $lot?->age !== null ? max(0, (int) $lot->age) : 0,
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
            'specification' => $effectiveSpec,
            'comp_specification' => $compSpec,

            'lifecycles' => $lifecycles,
        ];

        return [
            'id' => $this->id,
            'asset_id' => $assetId,
            'unit_id' => $assetId,
            'asset_code' => $asset->number ?? '-',
            'asset_number' => $asset->number ?? '-',
            'category' => $catName,
            'category_code' => $catCode,
            'model_category_code' => $catCode,
            'type' => $typeName,
            'subcategory' => $typeName,
            'brand' => $model?->brand?->name ?? '-',
            'name' => $model?->name ?? '-',
            'nama' => $model?->name ?? '-',
            'model_spec' => $model?->specification ?? '-',
            'barang_spec' => $model?->specification ?? '-',
            'specification' => $effectiveSpec,
            'comp_specification' => $compSpec,
            'classification' => $asset?->classification ?? 'Aset',
            'borrowing_type' => $asset?->borrowing_type,
            'organizer' => $asset?->organizer ?? ($lot?->organizer->name ?? '-'),
            'burden' => $asset?->burden,
            'project_no' => $asset?->project?->no_project,
            'project_name' => $asset?->project?->project_name,
            'updated_at' => $asset?->updated_at ? $asset->updated_at->format('d M Y, H:i') : null,
            'floor' => $asset?->floor,
            'room' => $asset?->room,
            'age' => $lot?->age !== null ? max(0, (int) $lot->age) : 0,
            'decision' => $this->decision,
            'note' => $this->note,
            'qc_pass' => $asset?->qc_pass,
            'last_qc' => $asset?->last_qc?->format('Y-m-d H:i:s'),
            'last_qc_formatted' => $asset?->last_qc?->format('d-m-Y'),
            'requested_by' => $this->requester->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d-m-Y H:i') : null,
            'approver_name' => $this->approver->name ?? null,
            'asset_details' => $details,
            'unit_details' => $details,
        ];
    }
}
