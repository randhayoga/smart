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
            'code'             => 'required|string|size:6|regex:/^VN\d{4}$/|unique:vendors,code',
            'name'             => 'required|string|max:255|unique:vendors,name',
            'address'          => 'required|string|max:255',
            'phone_number'     => 'required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'description'      => 'nullable|string|max:255',
            'contact_person_1' => 'nullable|string|max:255',
            'cp_email_1'       => 'nullable|email|max:255',
            'cp_phone_1'       => 'nullable|string|max:255',
            'contact_person_2' => 'nullable|string|max:255',
            'cp_email_2'       => 'nullable|email|max:255',
            'cp_phone_2'       => 'nullable|string|max:255',
        ]);

        Vendor::create($validated);

        return redirect()->back()->with('success', 'Vendor berhasil ditambahkan.');
    }

    /**
     * Update the specified vendor in storage.
     */
    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'code'             => 'required|string|size:6|regex:/^VN\d{4}$/|unique:vendors,code,' . $vendor->id,
            'name'             => 'required|string|max:255|unique:vendors,name,' . $vendor->id,
            'address'          => 'required|string|max:255',
            'phone_number'     => 'required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'description'      => 'nullable|string|max:255',
            'contact_person_1' => 'nullable|string|max:255',
            'cp_email_1'       => 'nullable|email|max:255',
            'cp_phone_1'       => 'nullable|string|max:255',
            'contact_person_2' => 'nullable|string|max:255',
            'cp_email_2'       => 'nullable|email|max:255',
            'cp_phone_2'       => 'nullable|string|max:255',
        ]);

        $vendor->update($validated);

        return redirect()->back()->with('success', 'Vendor berhasil diperbarui.');
    }

    /**
     * Remove the specified vendor from storage if not currently in use.
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        if (DB::table('lots')->where('vendor_id', $vendor->id)->exists()) {
            return redirect()->back()->with('error', 'Vendor tidak dapat dihapus karena sedang digunakan oleh data lot barang.');
        }

        $vendor->delete();

        return redirect()->back()->with('success', 'Vendor berhasil dihapus.');
    }
}
