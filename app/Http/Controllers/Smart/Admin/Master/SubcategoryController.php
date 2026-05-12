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
        $subcategory->delete();

        return redirect()->back()->with('success', 'Subkategori berhasil dihapus.');
    }
}
