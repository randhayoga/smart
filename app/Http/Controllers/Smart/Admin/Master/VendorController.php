<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vendor Controller managing supplier directories and contact person information.
 */
class VendorController extends Controller
{
    /**
     * Store a newly created vendor in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'             => 'required|string|size:7|regex:/^VN\d{5}$/|unique:vendors,code',
            'name'             => 'required|string|max:255|unique:vendors,name',
            'address'          => 'nullable|string|max:255',
            'phone_number'     => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s]+$/'],
            'email'            => 'nullable|email|max:255',
            'description'      => 'nullable|string|max:255',
            'contact_persons'  => 'nullable|array',
            'contact_persons.*.contact_person' => 'nullable|string|max:255',
            'contact_persons.*.cp_email'       => 'nullable|email|max:255',
            'contact_persons.*.cp_phone'       => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s]+$/'],
        ]);

        DB::transaction(function () use ($validated) {
            $vendor = Vendor::create([
                'code'         => $validated['code'],
                'name'         => $validated['name'],
                'address'      => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'email'        => $validated['email'] ?? null,
                'description'  => $validated['description'] ?? null,
            ]);

            if (!empty($validated['contact_persons'])) {
                $cps = collect($validated['contact_persons'])
                    ->filter(fn ($cp) => !empty($cp['contact_person']) || !empty($cp['cp_email']) || !empty($cp['cp_phone']))
                    ->map(fn ($cp) => [
                        'contact_person' => $cp['contact_person'] ?? null,
                        'cp_email'       => $cp['cp_email'] ?? null,
                        'cp_phone'       => $cp['cp_phone'] ?? null,
                    ])
                    ->values()
                    ->all();

                if (!empty($cps)) {
                    $vendor->contactPersons()->createMany($cps);
                }
            }
        });

        return redirect()->back()->with('success', __('master.vendors.created'));
    }

    /**
     * Update the specified vendor in storage.
     */
    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'code'             => 'required|string|size:7|regex:/^VN\d{5}$/|unique:vendors,code,' . $vendor->id,
            'name'             => 'required|string|max:255|unique:vendors,name,' . $vendor->id,
            'address'          => 'nullable|string|max:255',
            'phone_number'     => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s]+$/'],
            'email'            => 'nullable|email|max:255',
            'description'      => 'nullable|string|max:255',
            'contact_persons'  => 'nullable|array',
            'contact_persons.*.contact_person' => 'nullable|string|max:255',
            'contact_persons.*.cp_email'       => 'nullable|email|max:255',
            'contact_persons.*.cp_phone'       => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s]+$/'],
        ]);

        DB::transaction(function () use ($validated, $vendor) {
            $vendor->update([
                'code'         => $validated['code'],
                'name'         => $validated['name'],
                'address'      => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'email'        => $validated['email'] ?? null,
                'description'  => $validated['description'] ?? null,
            ]);

            $vendor->contactPersons()->delete();

            if (!empty($validated['contact_persons'])) {
                $cps = collect($validated['contact_persons'])
                    ->filter(fn ($cp) => !empty($cp['contact_person']) || !empty($cp['cp_email']) || !empty($cp['cp_phone']))
                    ->map(fn ($cp) => [
                        'contact_person' => $cp['contact_person'] ?? null,
                        'cp_email'       => $cp['cp_email'] ?? null,
                        'cp_phone'       => $cp['cp_phone'] ?? null,
                    ])
                    ->values()
                    ->all();

                if (!empty($cps)) {
                    $vendor->contactPersons()->createMany($cps);
                }
            }
        });

        return redirect()->back()->with('success', __('master.vendors.updated'));
    }

    /**
     * Remove the specified vendor from storage if not currently in use.
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        if (DB::table('lots')->where('legacy_vendor_id', $vendor->id)->exists()) {
            return redirect()->back()->with('error', __('master.vendors.cannot_delete_used'));
        }

        $vendor->delete();

        return redirect()->back()->with('success', __('master.vendors.deleted'));
    }
}
