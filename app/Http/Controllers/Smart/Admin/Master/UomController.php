<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UomController extends Controller
{
    /**
     * Store a newly created resource in storage.
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
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
     */
    public function destroy(Uom $uom): RedirectResponse
    {
        $uom->delete();

        return redirect()->back()->with('success', 'Satuan berhasil dihapus.');
    }
}
