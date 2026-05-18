<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'code'        => 'required|string|max:7|unique:subcategories,code',
            'name'        => 'required|string|max:255',
        ]);

        Subcategory::create($validated);

        return redirect()->back()->with('success', 'Subkategori berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $subcategory->update($validated);

        return redirect()->back()->with('success', 'Subkategori berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subcategory $subcategory): RedirectResponse
    {
        // Check if there are any Barangs linked to this subcategory
        if (\App\Models\Inventory\Barang::where('subcategory_id', $subcategory->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Gagal menghapus! Subkategori ini masih memiliki barang di dalamnya. Silakan hapus barang-barangnya terlebih dahulu.'
            ]);
        }

        $subcategory->delete();

        return redirect()->back()->with('success', 'Subkategori berhasil dihapus.');
    }
}
