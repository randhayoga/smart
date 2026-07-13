<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubcategoryController extends Controller
{
    /**
     * Menyimpan data subkategori baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $category = Category::find($request->category_id);
        $categoryCode = $category ? $category->code : '';

        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'code'        => [
                'required',
                'string',
                'max:9',
                'unique:subcategories,code',
                'regex:/^' . preg_quote($categoryCode, '/') . '-[A-Z]{4}$/i'
            ],
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        Subcategory::create($validated);

        return redirect()->back()->with('success', 'Subkategori berhasil ditambahkan.');
    }

    /**
     * Memperbarui data subkategori di dalam database.
     */
    public function update(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $subcategory->update($validated);

        return redirect()->back()->with('success', 'Subkategori berhasil diperbarui.');
    }

    /**
     * Menghapus data subkategori dari database jika tidak sedang digunakan.
     */
    public function destroy(Subcategory $subcategory): RedirectResponse
    {
        if (DB::table('barangs')->where('subcategory_id', $subcategory->id)->exists()) {
            return redirect()->back()->with('error', 'Subkategori tidak dapat dihapus karena sedang digunakan oleh data barang.');
        }

        $subcategory->delete();

        return redirect()->back()->with('success', 'Subkategori berhasil dihapus.');
    }
}
