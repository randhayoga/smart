<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Brand Controller managing CRUD operations for equipment brands and manufacturers.
 */
class BrandController extends Controller
{
    /**
     * Store a newly created brand in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:brands,name',
            'description' => 'nullable|string|max:255',
        ]);

        Brand::create($validated);

        return redirect()->back()->with('success', __('master.brands.created'));
    }

    /**
     * Update the specified brand in storage.
     */
    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:brands,name,' . $brand->id,
            'description' => 'nullable|string|max:255',
        ]);

        $brand->update($validated);

        return redirect()->back()->with('success', __('master.brands.updated'));
    }

    /**
     * Remove the specified brand from storage if not currently in use.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        if (DB::table('barangs')->where('brand_id', $brand->id)->exists()) {
            return redirect()->back()->with('error', __('master.brands.cannot_delete_used'));
        }

        $brand->delete();

        return redirect()->back()->with('success', __('master.brands.deleted'));
    }
}
