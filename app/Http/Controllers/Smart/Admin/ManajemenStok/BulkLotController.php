<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory\Lot;
use Illuminate\Support\Facades\Storage;

class BulkLotController extends Controller
{
    /**
     * Memperbarui beberapa data LOT secara massal di database.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:lots,id',
            'organizer_id' => 'nullable|exists:organizers,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'location_id' => 'nullable|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'unit_price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|max:1024',
            'use_parent_image' => 'nullable',
        ]);

        $lots = Lot::whereIn('id', $request->input('ids'))->get();
        $storedImagePath = null;

        foreach ($lots as $lot) {
            $lotData = [];

            if ($request->filled('organizer_id')) {
                $lotData['organizer_id'] = $request->input('organizer_id');
            }
            if ($request->filled('vendor_id')) {
                $lotData['vendor_id'] = $request->input('vendor_id');
            }
            if ($request->filled('location_id')) {
                $lotData['location_id'] = $request->input('location_id');
            }
            if ($request->has('floor_id')) {
                $lotData['floor_id'] = $request->input('floor_id');
            }
            if ($request->has('room_id')) {
                $lotData['room_id'] = $request->input('room_id');
            }
            if ($request->filled('unit_price')) {
                $lotData['unit_price'] = $request->input('unit_price');
            }

            if ($request->boolean('use_parent_image')) {
                if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                    $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                        || \App\Models\Inventory\Barang::where('image_url', $lot->image_url)->exists()
                        || \App\Models\Inventory\Unit::where('image_url', $lot->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('public')->delete($lot->image_url);
                    }
                }
                $barang = $lot->barang;
                if ($barang && $barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                    $lotData['image_url'] = $barang->image_url;
                }
            } else if ($request->hasFile('image_url')) {
                if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                    $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                        || \App\Models\Inventory\Barang::where('image_url', $lot->image_url)->exists()
                        || \App\Models\Inventory\Unit::where('image_url', $lot->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('public')->delete($lot->image_url);
                    }
                }
                if (!$storedImagePath) {
                    $storedImagePath = $request->file('image_url')->store('inventory', 'public');
                }
                $lotData['image_url'] = $storedImagePath;
            }

            if (!empty($lotData)) {
                $lot->update($lotData);
            }
        }

        return redirect()->back()->with('success', 'LOT terpilih berhasil diperbarui.');
    }

    /**
     * Menghapus beberapa data LOT secara massal di database beserta gambarnya.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:lots,id',
        ]);

        $lots = Lot::whereIn('id', $request->input('ids'))->get();

        $deletedCounter = 0;
        $undeletedCounter = 0;

        foreach ($lots as $lot) {
            if ($lot->units()->exists()) {
                $undeletedCounter++;
            } else {
                if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                    $isShared = Lot::where('image_url', $lot->image_url)
                        ->whereNotIn('id', $request->input('ids'))
                        ->exists()
                        || \App\Models\Inventory\Barang::where('image_url', $lot->image_url)->exists()
                        || \App\Models\Inventory\Unit::where('image_url', $lot->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('public')->delete($lot->image_url);
                    }
                }
                $lot->delete();
                $deletedCounter++;
            }
        }

        if ($undeletedCounter > 0) {
            $key = 'error';
            $message = $deletedCounter . ' LOT terpilih berhasil dihapus.' . "\n" . $undeletedCounter . ' LOT tidak dapat dihapus karena masih memiliki unit terkait.';
        } else {
            $key = 'success';
            $message = $deletedCounter . ' LOT terpilih berhasil dihapus.';
        }

        return redirect()->back()->with($key, $message);
    }
}
