<?php

namespace App\Http\Resources;

use App\Services\InventoryStockService;
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
     * Optional pre-computed stock map for high-performance batch transformation.
     *
     * @var array<string, int>|null
     */
    protected static ?array $batchStockMap = null;

    /**
     * Set batch stock map for resource transformations.
     */
    public static function setBatchStockMap(?array $map): void
    {
        static::$batchStockMap = $map;
    }

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

        $isAdminContext = ($request->user()?->isAdmin && ($request->routeIs('smart.inbox*') || $request->routeIs('smart.admin.*')));

        // Stock quantity calculation for warehouse inspection (Admin context only, secured against query tampering)
        if ($isAdminContext || $request->attributes->get('with_stock')) {
            $key = InventoryStockService::itemKey($this->barang_id, $this->subcategory_id);

            if (static::$batchStockMap !== null && array_key_exists($key, static::$batchStockMap)) {
                $availableStock = static::$batchStockMap[$key];
            } else {
                $stockService = app(InventoryStockService::class);
                $availableStock = $this->barang_id 
                    ? $stockService->getAvailableStockForBarang((int) $this->barang_id)
                    : $stockService->getAvailableStockForSubcategory((int) $this->subcategory_id);
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
