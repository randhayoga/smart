<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:locations,name',
        ]);

        Location::create($validated);

        return redirect()->back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:locations,name,' . $location->id,
        ]);

        $location->update($validated);

        return redirect()->back()->with('success', 'Lokasi berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location): RedirectResponse
    {
        if ($location->floors()->exists()) {
            return redirect()->back()->with('error', 'Lokasi tidak dapat dihapus karena masih memiliki lantai.');
        }

        if (\Illuminate\Support\Facades\DB::table('lots')->where('location_id', $location->id)->exists() ||
            \Illuminate\Support\Facades\DB::table('units')->where('location_id', $location->id)->exists()) {
            return redirect()->back()->with('error', 'Lokasi tidak dapat dihapus karena sedang digunakan oleh data lot/unit barang.');
        }

        $location->delete();

        return redirect()->back()->with('success', 'Lokasi berhasil dihapus.');
    }
}
