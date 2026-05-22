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
            'image_url' => 'nullable|image|max:1024',
        ]);

        $imagePath = 'inventory/lots/placeholder.jpg';
        if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory/lots', 'public');
        }
        $validated['image_url'] = $imagePath;

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
        ]);

        if ($request->hasFile('image_url')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                Storage::disk('public')->delete($lot->image_url);
            }
            $imagePath = $request->file('image_url')->store('inventory/lots', 'public');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

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
