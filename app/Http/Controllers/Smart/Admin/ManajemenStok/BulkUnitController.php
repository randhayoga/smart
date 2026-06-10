<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BulkUnitController extends Controller
{
    /**
     * Menyimpan data unit (aset) baru secara massal ke dalam database.
     */
    public function store(Request $request)
    {
        $rules = [
            'number' => 'required|string|max:255',
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'status' => 'required|string|max:255',
            'condition' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:999999999.99',
            'image_url' => 'required_without:use_lot_image|nullable|image|max:1024',
            'use_lot_image' => 'nullable',
            'bulk_quantity' => 'required|integer|min:1|max:999',
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

        $validated = $request->validate($rules, [
            'bulk_quantity.integer' => 'Tidak boleh desimal.',
        ]);

        $quantity = (int)$validated['bulk_quantity'];
        $baseNumber = $validated['number'];
        $suffixPos = strrpos($baseNumber, '-U');
        
        if ($suffixPos !== false) {
            $prefix = substr($baseNumber, 0, $suffixPos + 2); // e.g. "LOT-2026-CAT-SUB-XXXX-0001-U"
            $startNumStr = substr($baseNumber, $suffixPos + 2); // e.g. "06"
            $startNum = (int)$startNumStr;
            $padLength = strlen($startNumStr);
        } else {
            $prefix = $baseNumber . '-';
            $startNum = 1;
            $padLength = 2;
        }

        $generatedNumbers = [];
        for ($i = 0; $i < $quantity; $i++) {
            $num = $prefix . str_pad($startNum + $i, $padLength, '0', STR_PAD_LEFT);
            $generatedNumbers[] = $num;
        }

        $existing = Unit::whereIn('number', $generatedNumbers)->pluck('number')->toArray();
        if (!empty($existing)) {
            return redirect()->back()->withErrors(['number' => 'Beberapa Kode Aset sudah terpakai: ' . implode(', ', $existing)]);
        }

        $finalImagePath = null;
        if ($request->boolean('use_lot_image')) {
            if ($lot->image_url && Storage::disk('public')->exists($lot->image_url)) {
                $extension = pathinfo($lot->image_url, PATHINFO_EXTENSION);
                $newFilename = 'inventory/units/' . uniqid() . '.' . $extension;
                Storage::disk('public')->copy($lot->image_url, $newFilename);
                $finalImagePath = $newFilename;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $finalImagePath = $request->file('image_url')->store('inventory/units', 'public');
        }

        foreach ($generatedNumbers as $num) {
            Unit::create([
                'number' => $num,
                'lot_id' => $validated['lot_id'],
                'location_id' => $validated['location_id'],
                'floor_id' => $validated['floor_id'] ?? null,
                'room_id' => $validated['room_id'] ?? null,
                'status' => $validated['status'],
                'condition' => $validated['condition'],
                'price' => $validated['price'],
                'image_url' => $finalImagePath,
                'vehicle_registration' => $validated['vehicle_registration'] ?? null,
            ]);
        }

        return redirect()->back()->with('success', "Berhasil membuat {$quantity} aset secara otomatis.");
    }

    /**
     * Memperbarui beberapa unit (aset) sekaligus (bulk update).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:units,id',
            'status' => 'nullable|string',
            'condition' => 'nullable|string',
            'location_id' => 'nullable|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'use_lot_image' => 'nullable',
            'image_url' => 'nullable|image|max:1024',
        ]);

        $ids = $validated['ids'];
        $units = Unit::whereIn('id', $ids)->get();
        if ($units->isEmpty()) {
            return redirect()->back()->withErrors(['ids' => 'Tidak ada unit yang ditemukan.']);
        }

        $updateData = [];

        // 1. Status & Condition
        if ($request->filled('status')) {
            $updateData['status'] = $request->input('status');
        }
        if ($request->filled('condition')) {
            $updateData['condition'] = $request->input('condition');
        }

        // 2. Location, Floor, Room
        if ($request->filled('location_id')) {
            $updateData['location_id'] = $request->input('location_id');
            $updateData['floor_id'] = $request->input('floor_id');
            $updateData['room_id'] = $request->input('room_id');
        } else {
            if ($request->has('floor_id')) {
                $updateData['floor_id'] = $request->input('floor_id');
            }
            if ($request->has('room_id')) {
                $updateData['room_id'] = $request->input('room_id');
            }
        }

        // 3. Price
        if ($request->filled('price')) {
            $updateData['price'] = (float)$request->input('price');
        }

        // 4. Image URL / Use LOT Image
        $finalImagePath = null;
        $hasNewImage = false;

        if ($request->boolean('use_lot_image')) {
            $lot = Lot::findOrFail($units->first()->lot_id);
            if ($lot->image_url && Storage::disk('public')->exists($lot->image_url)) {
                $extension = pathinfo($lot->image_url, PATHINFO_EXTENSION);
                $newFilename = 'inventory/units/' . uniqid() . '.' . $extension;
                Storage::disk('public')->copy($lot->image_url, $newFilename);
                $finalImagePath = $newFilename;
                $hasNewImage = true;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $finalImagePath = $request->file('image_url')->store('inventory/units', 'public');
            $hasNewImage = true;
        }

        if ($hasNewImage) {
            $updateData['image_url'] = $finalImagePath;

            // Delete old images for each unit if they are not shared
            foreach ($units as $unit) {
                if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
                    $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                        || Lot::where('image_url', $unit->image_url)->exists()
                        || \App\Models\Inventory\Barang::where('image_url', $unit->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('public')->delete($unit->image_url);
                    }
                }
            }
        }

        if (!empty($updateData)) {
            Unit::whereIn('id', $ids)->update($updateData);
        }

        // 5. Handle approvals if status is changed to a status requiring approval
        $arrNeedApproval = ['rusak'];
        if ($request->filled('status') && in_array($request->input('status'), $arrNeedApproval)) {
            foreach ($units as $unit) {
                $existing = \App\Models\Inventory\UnitStatusApproval::where('unit_id', $unit->id)
                    ->where('decision', 'pending')
                    ->first();
                if (!$existing) {
                    \App\Models\Inventory\UnitStatusApproval::create([
                        'unit_id' => $unit->id,
                        'requester_id' => $request->user()->id,
                        'proposed_status' => $request->input('status'),
                        'decision' => 'pending',
                        'note' => null,
                        'approver_id' => null,
                        'requested_at' => now(),
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Aset berhasil diperbarui secara massal.');
    }
}
