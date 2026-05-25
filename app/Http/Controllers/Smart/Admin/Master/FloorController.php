<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Floor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FloorController extends Controller
{
    /**
     * Menyimpan data lantai baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'name'        => 'required|string|max:255',
        ]);

        Floor::create($validated);

        return redirect()->back()->with('success', 'Lantai berhasil ditambahkan.');
    }

    /**
     * Memperbarui data lantai di dalam database.
     */
    public function update(Request $request, Floor $floor): RedirectResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'name'        => 'required|string|max:255',
        ]);

        $floor->update($validated);

        return redirect()->back()->with('success', 'Lantai berhasil diperbarui.');
    }

    /**
     * Menghapus data lantai dari database jika tidak sedang digunakan.
     */
    public function destroy(Floor $floor): RedirectResponse
    {
        if ($floor->rooms()->exists()) {
            return redirect()->back()->with('error', 'Lantai tidak dapat dihapus karena masih memiliki ruangan.');
        }

        $floor->delete();

        return redirect()->back()->with('success', 'Lantai berhasil dihapus.');
    }
}
