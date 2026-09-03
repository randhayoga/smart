<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * External Approval Resource transforming request models for external HMAC-signed approval review.
 *
 * @mixin \App\Models\Request\Request
 */
class ExternalApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type_name;
        $destinationName = $this->destination_name;

        $firstItem = $this->items->first();
        $borrowPeriod = null;
        if ($firstItem && $firstItem->start_date) {
            $start = $firstItem->start_date->format('d-m-Y H:i');
            $end = $firstItem->end_date ? $firstItem->end_date->format('d-m-Y H:i') : '- (Tanpa Tenggat Waktu)';
            $borrowPeriod = "{$start} s.d. {$end}";
        }

        $items = $this->items->map(function ($item) {
            $brand = $item->barang?->brand?->name ?? '-';
            $name = $item->barang?->name ?? ($item->is_specific ? 'Barang' : 'Tidak Spesifik');
            $fullName = trim("{$brand} {$name}") ?: 'Barang';
            $spec = $item->barang?->specification ?? '';
            $subcategory = $item->subcategory?->name ?? $item->barang?->subcategory?->name ?? '-';
            $category = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $uom = $item->barang?->uom?->name ?? $item->subcategory?->barangs?->first()?->uom?->name ?? '';
            $rawImageUrl = $item->barang?->image_url ?? $item->subcategory?->barangs?->first()?->image_url ?? null;
            $imageUrl = $rawImageUrl ? '/media/' . $rawImageUrl : null;
            $isConsumable = (bool) ($item->barang?->is_consumable ?? $item->subcategory?->is_consumable ?? false);

            return [
                'id' => $item->id,
                'brand' => $brand,
                'name' => $name,
                'fullName' => $fullName,
                'subcategory' => $subcategory,
                'spec' => $spec,
                'category' => $category,
                'quantity' => $item->quantity_requested,
                'uom' => $uom,
                'imageUrl' => $imageUrl,
                'is_consumable' => $isConsumable,
            ];
        })->toArray();

        $approval = null;
        if ($this->approval) {
            $approval = [
                'approver_name' => $this->approval->approver?->name,
                'decision' => $this->approval->decision,
                'note' => $this->approval->note,
                'decided_at' => $this->approval->decided_at ? $this->approval->decided_at->format('d-m-Y H:i') : null,
            ];
        }

        return [
            'id' => $this->id,
            'number' => $this->request_number,
            'type' => $type,
            'requester' => $this->user?->name ?? 'Pengguna',
            'utilization' => $this->utilization,
            'destination' => $destinationName,
            'borrowPeriod' => $borrowPeriod,
            'reasoning' => $this->reasoning,
            'status' => $this->status,
            'rawStatus' => $this->status,
            'createdAt' => $this->created_at ? $this->created_at->format('d-m-Y H:i') : '-',
            'items' => $items,
            'approval' => $approval,
        ];
    }
}
