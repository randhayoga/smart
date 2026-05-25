<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    /**
     * Menyimpan data merek baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
        ]);

        Brand::create($validated);

        return redirect()->back()->with('success', 'Merek berhasil ditambahkan.');
    }

    /**
     * Memperbarui data merek di dalam database.
     */
    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
        ]);

        $brand->update($validated);

        return redirect()->back()->with('success', 'Merek berhasil diperbarui.');
    }

    /**
     * Menghapus data merek dari database jika tidak sedang digunakan.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        if (\Illuminate\Support\Facades\DB::table('barangs')->where('brand_id', $brand->id)->exists()) {
            return redirect()->back()->with('error', 'Merek tidak dapat dihapus karena sedang digunakan oleh data barang.');
        }

        $brand->delete();

        return redirect()->back()->with('success', 'Merek berhasil dihapus.');
    }
}
