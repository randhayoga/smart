<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar kategori (tidak digunakan secara langsung karena index utama ada di MasterController).
     */
    public function index(): Response
    {
        return Inertia::render('Smart/Admin/MasterData', [
            'categories' => Category::orderBy('code')->get(),
        ]);
    }

    /**
     * Menyimpan data kategori baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:categories,code',
            'name' => 'required|string|max:255',
            'is_consumable' => 'required|boolean',
        ]);

        Category::create($validated);

        return redirect()->back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    /**
     * Memperbarui data kategori di dalam database.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:categories,code,' . $category->id,
            'name' => 'required|string|max:255',
            'is_consumable' => 'required|boolean',
        ]);

        $category->update($validated);

        return redirect()->back()->with('success', 'Kategori berhasil diperbarui.');
    }

    /**
     * Menghapus data kategori dari database jika tidak sedang digunakan.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->subcategories()->exists()) {
            return redirect()->back()->with('error', 'Kategori tidak dapat dihapus karena masih memiliki subkategori.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Kategori berhasil dihapus.');
    }
}
