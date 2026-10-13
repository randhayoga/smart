<?php

namespace App\Http\Controllers\Smart\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Category;
use App\Models\Master\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Type Controller managing item types with code formatting validation.
 */
class TypeController extends Controller
{
    /**
     * Store a newly created type in storage.
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
                'unique:types,code',
                'regex:/^' . preg_quote($categoryCode, '/') . '-[A-Z]{4}$/i'
            ],
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:255',
            'is_consumable' => 'required|boolean',
        ]);

        Type::create($validated);

        return redirect()->back()->with('success', __('master.types.created'));
    }

    /**
     * Update the specified type in storage.
     */
    public function update(Request $request, Type $type): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:255',
            'is_consumable' => 'sometimes|required|boolean',
        ]);

        $type->update($validated);

        return redirect()->back()->with('success', __('master.types.updated'));
    }

    /**
     * Remove the specified type from storage if not currently in use.
     */
    public function destroy(Type $type): RedirectResponse
    {
        if (DB::table('models')->where('type_id', $type->id)->exists()) {
            return redirect()->back()->with('error', __('master.types.cannot_delete_used'));
        }

        $type->delete();

        return redirect()->back()->with('success', __('master.types.deleted'));
    }
}
