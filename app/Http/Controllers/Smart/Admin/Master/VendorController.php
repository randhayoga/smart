<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Menyimpan data vendor baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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
     * Memperbarui data vendor di dalam database.
     */
    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
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
     * Menghapus data vendor dari database jika tidak sedang digunakan.
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        if (\Illuminate\Support\Facades\DB::table('lots')->where('vendor_id', $vendor->id)->exists()) {
            return redirect()->back()->with('error', 'Vendor tidak dapat dihapus karena sedang digunakan oleh data lot barang.');
        }

        $vendor->delete();

        return redirect()->back()->with('success', 'Vendor berhasil dihapus.');
    }
}
