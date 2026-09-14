<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Barang Controller managing item type catalog CRUD operations, image storage, and low-stock alert triggers.
 */
class BarangController extends Controller
{
    /**
     * Menyimpan data barang baru ke dalam database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:255|unique:barangs',
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id' => 'required|exists:brands,id',
            'uom_id' => 'required|exists:uoms,id',
            'name' => 'required|string|max:255',
            'specification' => 'nullable|string|max:255',
            'min_stock_threshold' => 'nullable|integer|min:0',
            'image_url' => 'nullable|image|max:1024',
        ]);

        $imagePath = null;
        if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory', 'local');
            $validated['image_url'] = $imagePath;
        }

        $barang = Barang::create($validated);
        app(NotificationService::class)->checkAndNotifyLowStock($barang);

        return redirect()->back()->with('success', 'Tipe berhasil ditambahkan.');
    }

    /**
     * Memperbarui data barang yang sudah ada di database.
     */
    public function update(Request $request, Barang $barang)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:255|unique:barangs,number,' . $barang->id,
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id' => 'required|exists:brands,id',
            'uom_id' => 'required|exists:uoms,id',
            'name' => 'required|string|max:255',
            'specification' => 'nullable|string|max:255',
            'min_stock_threshold' => 'nullable|integer|min:0',
            'image_url' => 'nullable|image|max:1024',
            'available_stock' => 'nullable|integer|min:0',
        ]);

        if ($request->has('available_stock') && $request->input('available_stock') !== null) {
            $isConsumable = (bool)($barang->subcategory?->category?->is_consumable ?? false);
            if (!$isConsumable) {
                return back()->withErrors([
                    'available_stock' => 'Pengurangan stok hanya berlaku untuk barang habis pakai (consumable).',
                ]);
            }

            $currentStock = (int) $barang->lots()->sum('current_quantity');
            $newStock = (int) $request->input('available_stock');

            if ($newStock > $currentStock) {
                return back()->withErrors([
                    'available_stock' => "Stok hanya dapat dikurangi (maksimal {$currentStock}).",
                ]);
            }

            if ($newStock < $currentStock) {
                $deductQty = $currentStock - $newStock;
                DB::transaction(function () use ($barang, $deductQty, $request) {
                    $lots = $barang->lots()
                        ->where('current_quantity', '>', 0)
                        ->orderBy('date_of_receipt', 'asc')
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get();

                    $remaining = $deductQty;
                    foreach ($lots as $lot) {
                        if ($remaining <= 0) break;
                        $deductFromLot = min($lot->current_quantity, $remaining);
                        $prevQty = $lot->current_quantity;
                        $newLotQty = $prevQty - $deductFromLot;
                        $lot->update(['current_quantity' => $newLotQty]);

                        InventoryLog::create([
                            'barang_id' => $barang->id,
                            'lot_id' => $lot->id,
                            'user_id' => $request->user()?->id ?? 1,
                            'action_type' => 'adjustment',
                            'quantity_change' => -$deductFromLot,
                            'previous_state' => ['current_quantity' => $prevQty],
                            'new_state' => ['current_quantity' => $newLotQty],
                            'note' => 'Pengurangan stok tipe barang via edit tipe',
                            'created_at' => now(),
                        ]);

                        $remaining -= $deductFromLot;
                    }
                });
            }
        }
        unset($validated['available_stock']);

        if ($request->hasFile('image_url')) {
            if ($barang->image_url && Storage::disk('local')->exists($barang->image_url)) {
                $isShared = Barang::where('image_url', $barang->image_url)->where('id', '!=', $barang->id)->exists()
                    || Lot::where('image_url', $barang->image_url)->exists()
                    || Unit::where('image_url', $barang->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($barang->image_url);
                }
            }
            $imagePath = $request->file('image_url')->store('inventory', 'local');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        $barang->update($validated);
        app(NotificationService::class)->checkAndNotifyLowStock($barang);

        return redirect()->back()->with('success', 'Tipe berhasil diperbarui.');
    }


    /**
     * Menghapus data barang dari database beserta gambarnya.
     */
    public function destroy(Barang $barang)
    {
        if ($barang->lots()->exists()) {
            return redirect()->back()->with('error', 'Barang tidak dapat dihapus karena masih memiliki LOT terkait.');
        }

        if ($barang->image_url && Storage::disk('local')->exists($barang->image_url)) {
            $isShared = Barang::where('image_url', $barang->image_url)->where('id', '!=', $barang->id)->exists()
                || Lot::where('image_url', $barang->image_url)->exists()
                || Unit::where('image_url', $barang->image_url)->exists();
            if (!$isShared) {
                Storage::disk('local')->delete($barang->image_url);
            }
        }
        $barang->delete();

        return redirect()->route('smart.inventory')->with('success', 'Tipe berhasil dihapus.');
    }
}
