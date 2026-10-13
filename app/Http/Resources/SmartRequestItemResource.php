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
        $model = $this->model ?? $this->barang;
        $type = $this->type ?? $this->subcategory;

        $typeName = $model?->type?->name ?? $type?->name ?? '-';
        $catName = $model?->type?->category?->name ?? $type?->category?->name ?? '-';
        $isConsumable = (bool) $this->is_consumable;
        $imageUrl = $model?->image_url
            ? '/media/' . $model->image_url
            : (($firstModel = $type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null);
        $uomName = $model?->uom?->name 
            ?? $type?->models?->first()?->uom?->name 
            ?? 'satuan';

        $modelId = $this->model_id ?? $this->barang_id;
        $typeId = $this->type_id ?? $this->subcategory_id;

        $data = [
            'id' => $this->id,
            'model_id' => $modelId,
            'barang_id' => $modelId,
            'type_id' => $typeId,
            'subcategory_id' => $typeId,
            'type' => $typeName,
            'subcategory' => $typeName,
            'brand' => $model?->brand?->name ?? '-',
            'name' => $model?->name ?? 'Tidak Spesifik',
            'spec' => $model?->specification ?? '',
            'quantity' => $this->quantity_requested,
            'category' => $catName,
            'is_consumable' => $isConsumable,
            'imageUrl' => $imageUrl,
            'uom' => $uomName,
            'status' => $this->status,
        ];

        $isAdminContext = ($request->user()?->isAdmin && (
            $request->routeIs('smart.inbox*') || 
            $request->routeIs('smart.requests.*') || 
            $request->routeIs('smart.admin.*')
        ));

        // Stock quantity calculation for warehouse inspection (Admin context only, secured against query tampering)
        if ($isAdminContext || $request->attributes->get('with_stock')) {
            $key = InventoryStockService::itemKey($modelId, $typeId);

            if (static::$batchStockMap !== null && array_key_exists($key, static::$batchStockMap)) {
                $availableStock = static::$batchStockMap[$key];
            } else {
                $stockService = app(InventoryStockService::class);
                $availableStock = $modelId 
                    ? $stockService->getAvailableStockForModel((int) $modelId)
                    : $stockService->getAvailableStockForType((int) $typeId);
            }

            $data['stockQuantity'] = $availableStock;
            $data['stock'] = $availableStock;
        }

        // Assigned assets / serial numbers
        if ($this->relationLoaded('fulfillments')) {
            $data['assets'] = $this->fulfillments->map(function ($f) {
                return $f->asset?->number ?? $f->unit?->number;
            })->filter()->values()->toArray();
        }

        return $data;
    }
}
