<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use App\Models\Request\RequestUnitAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitController extends Controller
{
    /**
     * Menyimpan data unit (aset) baru ke dalam database.
     */
    public function store(Request $request)
    {
        $rules = [
            'number' => 'required|string|max:255|unique:units,number',
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'status' => 'required|string|max:255',
            'condition' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'image_url' => 'required_without:use_lot_image|nullable|image|max:1024',
            'use_lot_image' => 'nullable',
        ];

        $lot = Lot::with('barang.subcategory.category')->findOrFail($request->input('lot_id'));
        $isVehicle = false;
        if ($lot->barang && $lot->barang->subcategory && $lot->barang->subcategory->category) {
            $catName = strtolower($lot->barang->subcategory->category->name);
            $subcatName = strtolower($lot->barang->subcategory->name);
            $isVehicle = str_contains($catName, 'kendaraan') || str_contains($subcatName, 'kendaraan') ||
                         str_contains($catName, 'mobil') || str_contains($subcatName, 'mobil') ||
                         str_contains($catName, 'motor') || str_contains($subcatName, 'motor');
        }

        if ($isVehicle) {
            $rules['vehicle_registration'] = 'required|string|max:15';
        } else {
            $rules['vehicle_registration'] = 'nullable|string|max:15';
        }

        $validated = $request->validate($rules);

        // Single creation logic
        if ($request->boolean('use_lot_image')) {
            if ($lot->image_url && Storage::disk('public')->exists($lot->image_url)) {
                $extension = pathinfo($lot->image_url, PATHINFO_EXTENSION);
                $newFilename = 'inventory/units/' . uniqid() . '.' . $extension;
                Storage::disk('public')->copy($lot->image_url, $newFilename);
                $validated['image_url'] = $newFilename;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory/units', 'public');
            $validated['image_url'] = $imagePath;
        }

        unset($validated['use_lot_image']);

        Unit::create($validated);

        return redirect()->back()->with('success', 'Aset berhasil ditambahkan.');
    }

    /**
     * Memperbarui data unit (aset) yang sudah ada di database.
     */
    public function update(Request $request, Unit $unit)
    {
        $rules = [
            'number' => 'required|string|max:255|unique:units,number,' . $unit->id,
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'status' => 'required|string|max:255',
            'condition' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|image|max:1024',
            'use_lot_image' => 'nullable',
        ];

        $lot = Lot::with('barang.subcategory.category')->findOrFail($request->input('lot_id'));
        $isVehicle = false;
        if ($lot->barang && $lot->barang->subcategory && $lot->barang->subcategory->category) {
            $catName = strtolower($lot->barang->subcategory->category->name);
            $subcatName = strtolower($lot->barang->subcategory->name);
            $isVehicle = str_contains($catName, 'kendaraan') || str_contains($subcatName, 'kendaraan') ||
                         str_contains($catName, 'mobil') || str_contains($subcatName, 'mobil') ||
                         str_contains($catName, 'motor') || str_contains($subcatName, 'motor');
        }

        if ($isVehicle) {
            $rules['vehicle_registration'] = 'required|string|max:15';
        } else {
            $rules['vehicle_registration'] = 'nullable|string|max:15';
        }

        $validated = $request->validate($rules);

        if ($request->boolean('use_lot_image')) {
            if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
                $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                    || \App\Models\Inventory\Lot::where('image_url', $unit->image_url)->exists()
                    || \App\Models\Inventory\Barang::where('image_url', $unit->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($unit->image_url);
                }
            }
            $lot = Lot::findOrFail($request->input('lot_id'));
            if ($lot->image_url && Storage::disk('public')->exists($lot->image_url)) {
                $extension = pathinfo($lot->image_url, PATHINFO_EXTENSION);
                $newFilename = 'inventory/units/' . uniqid() . '.' . $extension;
                Storage::disk('public')->copy($lot->image_url, $newFilename);
                $validated['image_url'] = $newFilename;
            } else {
                $validated['image_url'] = null;
            }
        } else if ($request->hasFile('image_url')) {
            if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
                $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                    || \App\Models\Inventory\Lot::where('image_url', $unit->image_url)->exists()
                    || \App\Models\Inventory\Barang::where('image_url', $unit->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($unit->image_url);
                }
            }
            $imagePath = $request->file('image_url')->store('inventory/units', 'public');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        unset($validated['use_lot_image']);

        $unit->update($validated);

        return redirect()->back()->with('success', 'Aset berhasil diperbarui.');
    }

    /**
     * Menghapus data unit (aset) dari database beserta gambarnya.
     */
    public function destroy(Unit $unit)
    {
        if (RequestUnitAssignment::where('unit_id', $unit->id)->exists()) {
            return redirect()->back()->with('error', 'Aset tidak dapat dihapus karena sudah memiliki riwayat peminjaman/permintaan.');
        }

        if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
            $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                || \App\Models\Inventory\Lot::where('image_url', $unit->image_url)->exists()
                || \App\Models\Inventory\Barang::where('image_url', $unit->image_url)->exists();
            if (!$isShared) {
                Storage::disk('public')->delete($unit->image_url);
            }
        }

        $unit->delete();

        return redirect()->back()->with('success', 'Aset berhasil dihapus.');
    }

    /**
     * Memperbarui beberapa unit (aset) sekaligus (bulk update).
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:units,id',
            'status' => 'required|string',
            'condition' => 'required|string',
            'location_id' => 'required|string',
            'floor_id' => 'required|string',
            'room_id' => 'required|string',
        ]);

        $ids = $validated['ids'];
        $updateData = [];

        if ($validated['status'] !== 'keep') {
            $updateData['status'] = $validated['status'];
        }
        if ($validated['condition'] !== 'keep') {
            $updateData['condition'] = $validated['condition'];
        }
        if ($validated['location_id'] !== 'keep') {
            $updateData['location_id'] = $validated['location_id'] === 'null' ? null : $validated['location_id'];
            
            // If location is changing, floor/room must be cleared unless a specific new value is set
            $updateData['floor_id'] = ($validated['floor_id'] !== 'keep' && $validated['floor_id'] !== 'null') ? $validated['floor_id'] : null;
            $updateData['room_id'] = ($validated['room_id'] !== 'keep' && $validated['room_id'] !== 'null') ? $validated['room_id'] : null;
        } else {
            // Location is kept, but maybe floor/room are changed specifically
            if ($validated['floor_id'] !== 'keep') {
                $updateData['floor_id'] = $validated['floor_id'] === 'null' ? null : $validated['floor_id'];
            }
            if ($validated['room_id'] !== 'keep') {
                $updateData['room_id'] = $validated['room_id'] === 'null' ? null : $validated['room_id'];
            }
        }

        if (!empty($updateData)) {
            Unit::whereIn('id', $ids)->update($updateData);
        }

        return redirect()->back()->with('success', 'Aset berhasil diperbarui secara massal.');
    }
}
