<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Menyimpan data ruangan baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'floor_id' => 'required|integer|exists:floors,id',
            'name'     => 'required|string|max:255',
        ]);

        Room::create($validated);

        return redirect()->back()->with('success', 'Ruangan berhasil ditambahkan.');
    }

    /**
     * Memperbarui data ruangan di dalam database.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'floor_id' => 'required|integer|exists:floors,id',
            'name'     => 'required|string|max:255',
        ]);

        $room->update($validated);

        return redirect()->back()->with('success', 'Ruangan berhasil diperbarui.');
    }

    /**
     * Menghapus data ruangan dari database.
     */
    public function destroy(Room $room): RedirectResponse
    {
        $room->delete();

        return redirect()->back()->with('success', 'Ruangan berhasil dihapus.');
    }
}
