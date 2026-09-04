<?php

namespace App\Http\Resources;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin Approved Request Resource transforming approved requests for admin review modal and inbox.
 *
 * @mixin \App\Models\Request\Request
 */
class AdminApprovedRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusMap = [
            'wait' => 'Menunggu approval',
            'approve' => 'Di-approve',
            'confirm' => 'Dikonfirmasi Admin',
            'handover' => 'Serah Terima',
            'borrow' => 'Dipinjam',
            'return' => 'Dipinjam',
            'success' => 'Selesai',
            'reject' => 'Ditolak',
            'cancel' => 'Dibatalkan',
            'pending' => 'Pending',
            'partial' => 'Partial',
        ];

        $durationDays = 0;
        $durationHours = 0;
        if ($this->start_date && $this->end_date) {
            $diff = $this->start_date->diff($this->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $allStockSufficient = true;

        $items = $this->relationLoaded('items') ? $this->items->map(function ($item) use (&$allStockSufficient) {
            $barangId = $item->barang_id;
            $subcategoryId = $item->subcategory_id;

            // Calculate real-time warehouse available stock
            if ($barangId) {
                $hasAnyUnit = Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))->exists();
                if ($hasAnyUnit) {
                    $availableStock = (int) Unit::whereHas('lot', fn($q) => $q->where('barang_id', $barangId))
                        ->where('status', 'Tersedia')
                        ->count();
                } else {
                    $availableStock = (int) Lot::where('barang_id', $barangId)->sum('current_quantity');
                }
            } else {
                $hasAnyUnit = Unit::whereHas('lot.barang', fn($q) => $q->where('subcategory_id', $subcategoryId))->exists();
                if ($hasAnyUnit) {
                    $availableStock = (int) Unit::whereHas('lot.barang', fn($q) => $q->where('subcategory_id', $subcategoryId))
                        ->where('status', 'Tersedia')
                        ->count();
                } else {
                    $availableStock = (int) Lot::whereHas('barang', fn($q) => $q->where('subcategory_id', $subcategoryId))->sum('current_quantity');
                }
            }

            if ($availableStock < $item->quantity_requested) {
                $allStockSufficient = false;
            }

            $subcatName = $item->barang?->subcategory?->name ?? $item->subcategory?->name ?? '-';
            $catName = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $isConsumable = (bool) ($item->barang?->subcategory?->category?->is_consumable ?? $item->subcategory?->category?->is_consumable ?? false);
            $imageUrl = $item->barang?->image_url
                ? '/media/' . $item->barang->image_url
                : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null);
            $uomName = $item->barang?->uom?->name 
                ?? $item->subcategory?->barangs?->first()?->uom?->name 
                ?? 'satuan';

            return [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'subcategory' => $subcatName,
                'brand' => $item->barang?->brand?->name ?? '-',
                'name' => $item->barang?->name ?? 'Tidak Spesifik',
                'spec' => $item->barang?->specification ?? '',
                'quantity' => $item->quantity_requested,
                'stockQuantity' => $availableStock,
                'stock' => $availableStock,
                'category' => $catName,
                'is_consumable' => $isConsumable,
                'imageUrl' => $imageUrl,
                'uom' => $uomName,
                'status' => $item->status,
            ];
        })->toArray() : [];

        $pemanfaatanDetail = '-';
        if ($this->utilization === 'corporate') {
            $pemanfaatanDetail = $this->department?->org_name ?? $this->department?->name ?? '-';
        } else {
            if ($this->project) {
                $pemanfaatanDetail = $this->project->no_project 
                    ? "{$this->project->no_project} ({$this->project->project_name})" 
                    : ($this->project->project_name ?? '-');
            }
        }

        $createdAtFormatted = $this->created_at ? $this->created_at->format('d-m-Y H:i') : '-';

        return [
            'id' => $this->id,
            'number' => $this->request_number,
            'type' => $this->type_key,
            'requester' => $this->user->name ?? '-',
            'pemanfaatan' => $this->utilization,
            'pemanfaatanDetail' => $pemanfaatanDetail,
            'durationStart' => $this->start_date ? $this->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $this->end_date ? $this->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $statusMap[$this->status] ?? $this->status,
            'raw_status' => $this->status,
            'created_at' => $createdAtFormatted,
            'createdAt' => $createdAtFormatted,
            'approval_by' => $this->approval?->approver?->name,
            'approval_at' => $this->approval?->decided_at?->format('d-m-Y H:i'),
            'is_stock_sufficient' => $allStockSufficient,
            'items' => $items,
        ];
    }
}
