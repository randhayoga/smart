<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Services\InventoryLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Barang Controller managing item type catalog CRUD operations, image storage, and low-stock alert triggers.
 */
class BarangController extends Controller
{
    /**
     * Store a newly created barang item in storage.
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

        $imagePath = 'inventory/barangs/placeholder.jpg';
        if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory', 'local');
        }
        $validated['image_url'] = $imagePath;

        $barang = Barang::create($validated);
        if ($request->user()) {
            app(InventoryLogService::class)->logBarangCreated($barang, $request->user());
        }
        app(NotificationService::class)->checkAndNotifyLowStock($barang);

        return redirect()->back()->with('success', 'Tipe berhasil ditambahkan.');
    }

    /**
     * Update the specified barang item in storage.
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
        ]);

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

        $original = $barang->getAttributes();
        $barang->update($validated);

        if ($request->user()) {
            app(InventoryLogService::class)->logBarangUpdated($barang, $original, $request->user());
        }

        app(NotificationService::class)->checkAndNotifyLowStock($barang);

        return redirect()->back()->with('success', 'Tipe berhasil diperbarui.');
    }


    /**
     * Remove the specified barang item from storage along with its stored image.
     */
    public function destroy(Request $request, Barang $barang)
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

        if ($request->user()) {
            app(InventoryLogService::class)->prepareAndLogBarangDeleted($barang, $request->user());
        }

        $barang->delete();

        return redirect()->route('smart.inventory')->with('success', 'Tipe berhasil dihapus.');
    }
}
