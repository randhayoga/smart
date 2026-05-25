<?php

namespace App\Http\Controllers\Smart\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LotController extends Controller
{
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
            'po_number' => 'required|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'required|numeric|min:0',
            'image_url' => 'nullable|image|max:1024',
            'use_parent_image' => 'nullable',
        ]);

        if ($request->boolean('use_parent_image')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                Storage::disk('public')->delete($lot->image_url);
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
                Storage::disk('public')->delete($lot->image_url);
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

    public function destroy(Lot $lot)
    {
        if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
            Storage::disk('public')->delete($lot->image_url);
        }
        $lot->delete();

        return redirect()->back()->with('success', 'LOT berhasil dihapus.');
    }
}
