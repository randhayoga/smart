<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizerController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizers,name',
        ]);

        Organizer::create($validated);

        return redirect()->back()->with('success', 'Organizer berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizers,name,' . $organizer->id,
        ]);

        $organizer->update($validated);

        return redirect()->back()->with('success', 'Organizer berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organizer $organizer): RedirectResponse
    {
        $organizer->delete();

        return redirect()->back()->with('success', 'Organizer berhasil dihapus.');
    }
}
