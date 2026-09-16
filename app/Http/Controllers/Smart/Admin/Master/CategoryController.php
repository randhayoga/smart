<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Category Controller managing main inventory category classifications (consumable vs non-consumable).
 */
class CategoryController extends Controller
{
    /**
     * Display category listing (not accessed directly as primary view is rendered by MasterController).
     */
    public function index(): Response
    {
        return Inertia::render('Smart/Admin/MasterData', [
            'categories' => Category::orderBy('code')->get(),
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:4|unique:categories,code',
            'name' => 'required|string|max:255',
            'is_consumable' => 'required|boolean',
        ]);

        Category::create($validated);

        return redirect()->back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:4|unique:categories,code,' . $category->id,
            'name' => 'required|string|max:255',
            'is_consumable' => 'required|boolean',
        ]);

        $category->update($validated);

        return redirect()->back()->with('success', 'Kategori berhasil diperbarui.');
    }

    /**
     * Remove the specified category from storage if not currently in use.
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
