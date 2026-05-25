<?php

namespace App\Http\Controllers\Smart\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BarangController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:255|unique:barangs',
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id' => 'required|exists:brands,id',
            'uom_id' => 'required|exists:uoms,id',
            'specification' => 'required|string|max:255',
            'image_url' => 'nullable|image|max:1024',
        ]);

        $imagePath = null;
        if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory/barangs', 'public');
            $validated['image_url'] = $imagePath;
        }

        Barang::create($validated);

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function update(Request $request, Barang $barang)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:255|unique:barangs,number,' . $barang->id,
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id' => 'required|exists:brands,id',
            'uom_id' => 'required|exists:uoms,id',
            'specification' => 'required|string|max:255',
            'image_url' => 'nullable|image|max:1024',
        ]);

        if ($request->hasFile('image_url')) {
            if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                Storage::disk('public')->delete($barang->image_url);
            }
            $imagePath = $request->file('image_url')->store('inventory/barangs', 'public');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        $barang->update($validated);

        return redirect()->back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Barang $barang)
    {
        if ($barang->lots()->exists()) {
            return redirect()->back()->with('error', 'Barang tidak dapat dihapus karena masih memiliki LOT terkait.');
        }

        if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
            Storage::disk('public')->delete($barang->image_url);
        }
        $barang->delete();

        return redirect()->route('smart.inventory')->with('success', 'Barang berhasil dihapus.');
    }
}
