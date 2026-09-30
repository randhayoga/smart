<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Organizer Controller managing asset custodian and inventory organizer records.
 */
class OrganizerController extends Controller
{
    /**
     * Store a newly created organizer in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizers,name',
        ]);

        Organizer::create($validated);

        return redirect()->back()->with('success', __('master.organizers.created'));
    }

    /**
     * Update the specified organizer in storage.
     */
    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizers,name,' . $organizer->id,
        ]);

        $organizer->update($validated);

        return redirect()->back()->with('success', __('master.organizers.updated'));
    }

    /**
     * Remove the specified organizer from storage if not currently in use.
     */
    public function destroy(Organizer $organizer): RedirectResponse
    {
        if (DB::table('lots')->where('organizer_id', $organizer->id)->exists()) {
            return redirect()->back()->with('error', __('master.organizers.cannot_delete_used'));
        }

        $organizer->delete();

        return redirect()->back()->with('success', __('master.organizers.deleted'));
    }
}
