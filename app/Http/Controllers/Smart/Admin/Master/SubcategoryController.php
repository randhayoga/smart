<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subcategory Controller managing item subcategories with code formatting validation.
 */
class SubcategoryController extends Controller
{
    /**
     * Store a newly created subcategory in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $category = Category::find($request->category_id);
        $categoryCode = $category ? $category->code : '';

        $validated = $request->validate([
            'category_id'   => 'required|integer|exists:categories,id',
            'code'          => [
                'required',
                'string',
                'max:9',
                'unique:subcategories,code',
                'regex:/^' . preg_quote($categoryCode, '/') . '-[A-Z]{4}$/i'
            ],
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:255',
            'is_consumable' => 'required|boolean',
        ]);

        Subcategory::create($validated);

        return redirect()->back()->with('success', __('master.subcategories.created'));
    }

    /**
     * Update the specified subcategory in storage.
     */
    public function update(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:255',
            'is_consumable' => 'sometimes|required|boolean',
        ]);

        $subcategory->update($validated);

        return redirect()->back()->with('success', __('master.subcategories.updated'));
    }

    /**
     * Remove the specified subcategory from storage if not currently in use.
     */
    public function destroy(Subcategory $subcategory): RedirectResponse
    {
        if (DB::table('barangs')->where('subcategory_id', $subcategory->id)->exists()) {
            return redirect()->back()->with('error', __('master.subcategories.cannot_delete_used'));
        }

        $subcategory->delete();

        return redirect()->back()->with('success', __('master.subcategories.deleted'));
    }
}
