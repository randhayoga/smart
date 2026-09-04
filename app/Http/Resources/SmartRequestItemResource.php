<?php

namespace App\Http\Resources;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Smart Request Item Resource transforming request items with fallback metadata, warehouse stock calculation, and assigned serial numbers.
 *
 * @mixin \App\Models\Request\RequestItem
 */
class SmartRequestItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subcatName = $this->barang?->subcategory?->name ?? $this->subcategory?->name ?? '-';
        $catName = $this->barang?->subcategory?->category?->name ?? $this->subcategory?->category?->name ?? '-';
        $isConsumable = (bool) ($this->barang?->subcategory?->category?->is_consumable ?? $this->subcategory?->category?->is_consumable ?? false);
        $imageUrl = $this->barang?->image_url
            ? '/media/' . $this->barang->image_url
            : (($firstBarang = $this->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null);
        $uomName = $this->barang?->uom?->name 
            ?? $this->subcategory?->barangs?->first()?->uom?->name 
            ?? 'satuan';

        $data = [
            'id' => $this->id,
            'barang_id' => $this->barang_id,
            'subcategory' => $subcatName,
            'brand' => $this->barang?->brand?->name ?? '-',
            'name' => $this->barang?->name ?? 'Tidak Spesifik',
            'spec' => $this->barang?->specification ?? '',
            'quantity' => $this->quantity_requested,
            'category' => $catName,
            'is_consumable' => $isConsumable,
            'imageUrl' => $imageUrl,
            'uom' => $uomName,
            'status' => $this->status,
        ];

        // Stock quantity calculation for warehouse inspection (Admin context)
        if ($request->routeIs('smart.admin.*') || $request->has('with_stock')) {
            $barangId = $this->barang_id;
            $subcategoryId = $this->subcategory_id;

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

            $data['stockQuantity'] = $availableStock;
            $data['stock'] = $availableStock;
        }

        // Assigned assets / serial numbers
        if ($this->relationLoaded('unitAssignments')) {
            $data['assets'] = $this->unitAssignments->pluck('unit.number')->filter()->values()->toArray();
        }

        return $data;
    }
}
