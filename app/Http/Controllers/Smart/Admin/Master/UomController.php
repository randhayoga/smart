<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UOM Controller managing unit of measure definitions (e.g., PCS, UNIT, ROLL).
 */
class UomController extends Controller
{
    /**
     * Store a newly created unit of measure (UOM) in storage.
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
     * Update the specified unit of measure (UOM) in storage.
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
     * Remove the specified unit of measure (UOM) from storage if not currently in use.
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
