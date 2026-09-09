<?php

namespace App\Actions\Request;

use App\Models\Inventory\Lot;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to manually assign or update consumable LOT allocations for a request item.
 * Preserves already handed-over fulfillments and guards against over-allocation.
 */
class AssignLotsToRequestItem
{
    /**
     * Execute consumable lot assignment for an item.
     *
     * @param RequestItem $item
     * @param array<array{lot_id: int, quantity: int}> $lotAllocations
     * @return Collection
     * @throws ValidationException
     */
    public function execute(RequestItem $item, array $lotAllocations): Collection
    {
        $item->loadMissing(['request', 'barang', 'subcategory']);

        $quantityRequested = (int) $item->quantity_requested;

        // Normalize allocations: filter out invalid entries or quantities <= 0
        $cleanAllocations = [];
        $totalAllocated = 0;

        foreach ($lotAllocations as $alloc) {
            $lotId = (int) ($alloc['lot_id'] ?? 0);
            $qty = (int) ($alloc['quantity'] ?? 0);

            if ($lotId <= 0) {
                continue;
            }

            if ($qty < 0) {
                throw ValidationException::withMessages([
                    'lot_allocations' => ['Jumlah alokasi stok tidak boleh bernilai negatif.'],
                ]);
            }

            if ($qty > 0) {
                if (isset($cleanAllocations[$lotId])) {
                    $cleanAllocations[$lotId] += $qty;
                } else {
                    $cleanAllocations[$lotId] = $qty;
                }
                $totalAllocated += $qty;
            }
        }

        if ($totalAllocated > $quantityRequested) {
            throw ValidationException::withMessages([
                'lot_allocations' => ["Total alokasi ({$totalAllocated}) melebihi jumlah yang diminta ({$quantityRequested})."],
            ]);
        }

        return DB::transaction(function () use ($item, $cleanAllocations, $totalAllocated) {
            // 1. Identify locked / handed-over fulfillments
            $lockedFulfillments = RequestFulfillment::where('request_item_id', $item->id)
                ->whereNotNull('lot_id')
                ->where(function ($q) {
                    $q->whereNotNull('handover_id')
                      ->orWhereNotNull('completed_at');
                })
                ->get();

            $lockedLotMap = $lockedFulfillments->pluck('quantity_fulfilled', 'lot_id')->all();

            // 2. Validate lots existence, matching category, and available capacity
            if (!empty($cleanAllocations)) {
                $lotIds = array_keys($cleanAllocations);
                $lots = Lot::with('barang')->whereIn('id', $lotIds)->get()->keyBy('id');

                if ($lots->count() !== count($lotIds)) {
                    throw ValidationException::withMessages([
                        'lot_allocations' => ['Satu atau lebih LOT tidak ditemukan.'],
                    ]);
                }

                foreach ($cleanAllocations as $lotId => $qty) {
                    $lot = $lots[$lotId];

                    // Check that LOT belongs to requested item
                    $lotBarangId = $lot->barang_id;
                    $itemBarangId = $item->barang_id;
                    $itemSubcatId = $item->subcategory_id ?? $item->barang?->subcategory_id;
                    $lotSubcatId = $lot->barang?->subcategory_id;

                    $matches = false;
                    if ($itemBarangId && (int)$lotBarangId === (int)$itemBarangId) {
                        $matches = true;
                    } elseif ($itemSubcatId && (int)$lotSubcatId === (int)$itemSubcatId) {
                        $matches = true;
                    }

                    if (!$matches) {
                        throw ValidationException::withMessages([
                            'lot_allocations' => ["LOT {$lot->number} tidak cocok dengan tipe barang yang diminta."],
                        ]);
                    }

                    // Calculate max available in this LOT
                    $existingOnItem = RequestFulfillment::where('request_item_id', $item->id)
                        ->where('lot_id', $lotId)
                        ->whereNull('unit_id')
                        ->first();

                    $alreadyOnItem = $existingOnItem ? (int)$existingOnItem->quantity_fulfilled : 0;
                    $maxStock = (int)$lot->current_quantity + $alreadyOnItem;

                    if ($qty > $maxStock) {
                        throw ValidationException::withMessages([
                            'lot_allocations' => ["Alokasi untuk LOT {$lot->number} ({$qty}) melebihi stok yang tersedia ({$maxStock})."],
                        ]);
                    }
                }
            }

            // 3. Delete non-locked fulfillments that are no longer in cleanAllocations
            $keptLotIds = array_keys($cleanAllocations);
            RequestFulfillment::where('request_item_id', $item->id)
                ->whereNotNull('lot_id')
                ->whereNull('unit_id')
                ->whereNotIn('id', $lockedFulfillments->pluck('id'))
                ->whereNotIn('lot_id', $keptLotIds)
                ->delete();

            // 4. Update or insert fulfillments for cleanAllocations
            foreach ($cleanAllocations as $lotId => $qty) {
                $existing = RequestFulfillment::where('request_item_id', $item->id)
                    ->where('lot_id', $lotId)
                    ->whereNull('unit_id')
                    ->first();

                if ($existing) {
                    if (!$existing->handover_id && !$existing->completed_at) {
                        $existing->update([
                            'quantity_fulfilled' => $qty,
                            'assigned_at' => now(),
                        ]);
                    }
                } else {
                    RequestFulfillment::create([
                        'request_item_id' => $item->id,
                        'lot_id' => $lotId,
                        'unit_id' => null,
                        'quantity_fulfilled' => $qty,
                        'assigned_at' => now(),
                    ]);
                }
            }

            return RequestFulfillment::where('request_item_id', $item->id)->whereNotNull('lot_id')->with('lot.barang')->get();
        });
    }
}
