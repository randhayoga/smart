<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Location Controller managing physical branches, offices, and site location records.
 */
class LocationController extends Controller
{
    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => [
                'required', 'string', 'max:255',
                Rule::unique('locations', 'name')->where(function ($query) use ($request) {
                    return $query->where('parent_id', $request->input('parent_id'));
                }),
            ],
            'parent_id' => 'nullable|integer|exists:locations,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Location::create($validated);

        return redirect()->back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    /**
     * Update the specified location in storage.
     */
    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => [
                'required', 'string', 'max:255',
                Rule::unique('locations', 'name')->where(function ($query) use ($request) {
                    return $query->where('parent_id', $request->input('parent_id'));
                })->ignore($location->id),
            ],
            'parent_id' => 'nullable|integer|exists:locations,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        // Prevent circular hierarchy
        if (!empty($validated['parent_id'])) {
            if ((int)$validated['parent_id'] === (int)$location->id) {
                return redirect()->back()->withErrors(['parent_id' => 'Lokasi tidak dapat menjadi induk bagi dirinya sendiri.']);
            }
            if (in_array((int)$validated['parent_id'], $location->allChildrenIds(), true)) {
                return redirect()->back()->withErrors(['parent_id' => 'Lokasi tidak dapat dipindahkan ke bawah sub-lokasinya sendiri.']);
            }
        }

        $location->update($validated);

        return redirect()->back()->with('success', 'Lokasi berhasil diperbarui.');
    }

    /**
     * Toggle active/inactive status of the specified location.
     */
    public function toggleActive(Request $request, Location $location): RedirectResponse
    {
        $newStatus = !$location->is_active;
        $location->update(['is_active' => $newStatus]);
        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Lokasi berhasil {$statusText}.");
    }

    /**
     * Remove the specified location from storage if not currently in use.
     */
    public function destroy(Location $location): RedirectResponse
    {
        if ($location->children()->exists()) {
            return redirect()->back()->with('error', 'Lokasi tidak dapat dihapus karena masih memiliki sub-lokasi.');
        }

        if (DB::table('lots')->where('location_id', $location->id)->exists() ||
            DB::table('units')->where('location_id', $location->id)->exists()) {
            return redirect()->back()->with('error', 'Lokasi tidak dapat dihapus karena sedang digunakan oleh data lot/unit barang.');
        }

        $location->delete();

        return redirect()->back()->with('success', 'Lokasi berhasil dihapus.');
    }
}
