<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UomController extends Controller
{
    /**
     * Menyimpan data satuan (UOM) baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:uoms,name',
        ]);

        Uom::create($validated);

        return redirect()->back()->with('success', 'Satuan berhasil ditambahkan.');
    }

    /**
     * Memperbarui data satuan (UOM) di dalam database.
     */
    public function update(Request $request, Uom $uom): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:uoms,name,' . $uom->id,
        ]);

        $uom->update($validated);

        return redirect()->back()->with('success', 'Satuan berhasil diperbarui.');
    }

    /**
     * Menghapus data satuan (UOM) dari database jika tidak sedang digunakan.
     */
    public function destroy(Uom $uom): RedirectResponse
    {
        if (DB::table('barangs')->where('uom_id', $uom->id)->exists()) {
            return redirect()->back()->with('error', 'Satuan tidak dapat dihapus karena sedang digunakan oleh data barang.');
        }

        $uom->delete();

        return redirect()->back()->with('success', 'Satuan berhasil dihapus.');
    }
}
