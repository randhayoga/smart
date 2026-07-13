<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BulkBarangController extends Controller
{
    /**
     * Memperbarui beberapa data barang secara massal di database.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:barangs,id',
            'brand_id' => 'nullable|exists:brands,id',
            'uom_id' => 'nullable|exists:uoms,id',
            'name' => 'nullable|string|max:255',
            'specification' => 'nullable|string|max:255',
            'image_url' => 'nullable|image|max:1024',
        ]);

        $updateData = [];

        if ($request->filled('brand_id')) {
            $updateData['brand_id'] = $request->input('brand_id');
        }
        if ($request->filled('uom_id')) {
            $updateData['uom_id'] = $request->input('uom_id');
        }
        if ($request->filled('name')) {
            $updateData['name'] = $request->input('name');
        }
        if ($request->filled('specification')) {
            $updateData['specification'] = $request->input('specification');
        }

        if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory', 'public');
            $updateData['image_url'] = $imagePath;

            // Delete old images of updated barangs if they are not shared
            $barangs = Barang::whereIn('id', $request->input('ids'))->get();
            foreach ($barangs as $barang) {
                if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                    $isShared = Barang::where('image_url', $barang->image_url)
                        ->whereNotIn('id', $request->input('ids'))
                        ->exists()
                        || Lot::where('image_url', $barang->image_url)->exists()
                        || Unit::where('image_url', $barang->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('public')->delete($barang->image_url);
                    }
                }
            }
        }

        if (!empty($updateData)) {
            Barang::whereIn('id', $request->input('ids'))->update($updateData);
        }

        $count = count($request->input('ids'));
        return redirect()->back()->with('success', $count . ' tipe terpilih berhasil diperbarui.');
    }

    /**
     * Menghapus beberapa data barang secara massal di database beserta gambarnya.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:barangs,id',
        ]);

        $barangs = Barang::whereIn('id', $request->input('ids'))->get();

        $deletedCounter = 0;
        $undeletedCounter = 0;

        foreach ($barangs as $barang) {
            if ($barang->lots()->exists()) {
                $undeletedCounter++;
            } else {
                if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                    $isShared = Barang::where('image_url', $barang->image_url)
                        ->whereNotIn('id', $request->input('ids'))
                        ->exists()
                        || Lot::where('image_url', $barang->image_url)->exists()
                        || Unit::where('image_url', $barang->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('public')->delete($barang->image_url);
                    }
                }
                $barang->delete();
                $deletedCounter++;
            }
        }

        if ($undeletedCounter > 0) {
            $key = 'error';
            $message = $deletedCounter . ' barang terpilih berhasil dihapus.' . "\n" . $undeletedCounter . ' barang tidak dapat dihapus karena memiliki LOT terkait.';
        } else {
            $key = 'success';
            $message = $deletedCounter . ' barang terpilih berhasil dihapus.';
        }

        return redirect()->back()->with($key, $message);
    }
}
