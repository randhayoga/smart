<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LotController extends Controller
{
    /**
     * Menyimpan data LOT baru ke dalam database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:26|unique:lots,number',
            'barang_id' => 'required|exists:barangs,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => 'required|exists:vendors,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'initial_quantity' => 'nullable|integer|min:0',
            'current_quantity' => 'nullable|integer|min:0',
            'po_number' => 'required|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'required|numeric|min:0',
            'image_url' => 'required_without:use_parent_image|nullable|image|max:1024',
            'use_parent_image' => 'nullable',
        ]);

        if ($request->boolean('use_parent_image')) {
            $barang = \App\Models\Inventory\Barang::findOrFail($request->input('barang_id'));
            if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                $extension = pathinfo($barang->image_url, PATHINFO_EXTENSION);
                $newFilename = 'inventory/lots/' . uniqid() . '.' . $extension;
                Storage::disk('public')->copy($barang->image_url, $newFilename);
                $validated['image_url'] = $newFilename;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto barang parent tidak ditemukan di storage.']);
            }
        } else {
            $imagePath = $request->file('image_url')->store('inventory/lots', 'public');
            $validated['image_url'] = $imagePath;
        }

        unset($validated['use_parent_image']);

        Lot::create($validated);

        return redirect()->back()->with('success', 'LOT berhasil ditambahkan.');
    }

    /**
     * Memperbarui data LOT yang sudah ada di database.
     */
    public function update(Request $request, Lot $lot)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:26|unique:lots,number,' . $lot->id,
            'barang_id' => 'required|exists:barangs,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => 'required|exists:vendors,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'initial_quantity' => 'nullable|integer|min:0',
            'current_quantity' => 'nullable|integer|min:0',
            'po_number' => 'required|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'required|numeric|min:0',
            'image_url' => 'nullable|image|max:1024',
            'use_parent_image' => 'nullable',
        ]);

        if ($request->boolean('use_parent_image')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || \App\Models\Inventory\Barang::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($lot->image_url);
                }
            }
            $barang = \App\Models\Inventory\Barang::findOrFail($request->input('barang_id'));
            if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                $extension = pathinfo($barang->image_url, PATHINFO_EXTENSION);
                $newFilename = 'inventory/lots/' . uniqid() . '.' . $extension;
                Storage::disk('public')->copy($barang->image_url, $newFilename);
                $validated['image_url'] = $newFilename;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto barang parent tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || \App\Models\Inventory\Barang::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($lot->image_url);
                }
            }
            $imagePath = $request->file('image_url')->store('inventory/lots', 'public');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        unset($validated['use_parent_image']);

        $lot->update($validated);

        return redirect()->back()->with('success', 'LOT berhasil diperbarui.');
    }

    /**
     * Menghapus data LOT dari database beserta gambarnya.
     */
    public function destroy(Lot $lot)
    {
        if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
            $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                || \App\Models\Inventory\Barang::where('image_url', $lot->image_url)->exists();
            if (!$isShared) {
                Storage::disk('public')->delete($lot->image_url);
            }
        }
        $lot->delete();

        return redirect()->back()->with('success', 'LOT berhasil dihapus.');
    }

    /**
     * Menampilkan detail data LOT dalam format JSON.
     */
    public function show(Lot $lot)
    {
        $lot->load([
            'barang.subcategory.category',
            'barang.brand',
            'barang.uom',
            'organizer',
            'vendor',
            'location',
            'floor',
            'room'
        ]);

        return response()->json([
            'id' => $lot->id,
            'lotCode' => $lot->number,
            'poNumber' => $lot->po_number,
            'entryDate' => $lot->date_of_receipt ? $lot->date_of_receipt->format('d/m/Y') : '-',
            'organizer' => $lot->organizer->name ?? '-',
            'organizer_id' => $lot->organizer_id,
            'vendor' => $lot->vendor->name ?? '-',
            'vendor_id' => $lot->vendor_id,
            'location' => $lot->location->name ?? '-',
            'location_id' => $lot->location_id,
            'floor' => $lot->floor->name ?? null,
            'floor_id' => $lot->floor_id,
            'room' => $lot->room->name ?? null,
            'room_id' => $lot->room_id,
            'unitPrice' => $lot->unit_price,
            'imageUrl' => $lot->image_url,
            'initial_quantity' => $lot->initial_quantity,
            'current_quantity' => $lot->current_quantity,
            'updated_at' => $lot->updated_at ? $lot->updated_at->format('d/m/Y H:i') : '-',
            
            // Parent barang info
            'barang_code' => $lot->barang->number ?? '-',
            'barang_brand' => $lot->barang->brand->name ?? '-',
            'barang_specification' => $lot->barang->specification ?? '-',
            'barang_category' => $lot->barang->subcategory->category->name ?? '-',
            'barang_subcategory' => $lot->barang->subcategory->name ?? '-',
            'barang_uom' => $lot->barang->uom->name ?? '-',
        ]);
    }
}
