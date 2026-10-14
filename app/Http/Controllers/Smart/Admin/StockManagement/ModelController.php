<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Master\Type;
use App\Services\InventoryLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Model Controller managing catalog model CRUD operations, image storage, and low-stock alert triggers.
 */
class ModelController extends Controller
{
    /**
     * Store a newly created model in storage.
     */
    public function store(Request $request)
    {
        if (!$request->has('type_id') && $request->has('subcategory_id')) {
            $request->merge(['type_id' => $request->input('subcategory_id')]);
        }

        $validated = $request->validate([
            'number' => 'required|string|max:50|unique:models',
            'type_id' => 'required|exists:types,id',
            'brand_id' => 'required|exists:brands,id',
            'uom_id' => 'required|exists:uoms,id',
            'name' => 'required|string|max:255',
            'specification' => 'nullable|string|max:255',
            'min_stock_threshold' => 'nullable|integer|min:0',
        ]);

        $type = Type::with('category')->find($validated['type_id']);
        if ($type?->category?->code === 'COMP') {
            $validated['specification'] = null;
        }

        $model = Model::create($validated);
        if ($request->user()) {
            app(InventoryLogService::class)->logModelCreated($model, $request->user());
        }
        app(NotificationService::class)->checkAndNotifyLowStock($model);

        return redirect()->back()->with('success', __('inventory.model_created'));
    }

    /**
     * Update the specified model in storage.
     */
    public function update(Request $request, Model $model)
    {
        if (!$request->has('type_id') && $request->has('subcategory_id')) {
            $request->merge(['type_id' => $request->input('subcategory_id')]);
        }

        $validated = $request->validate([
            'number' => 'required|string|max:50|unique:models,number,' . $model->id,
            'type_id' => 'required|exists:types,id',
            'brand_id' => 'required|exists:brands,id',
            'uom_id' => 'required|exists:uoms,id',
            'name' => 'required|string|max:255',
            'specification' => 'nullable|string|max:255',
            'min_stock_threshold' => 'nullable|integer|min:0',
        ]);

        $type = Type::with('category')->find($validated['type_id']);
        if ($type?->category?->code === 'COMP') {
            $validated['specification'] = null;
        }

        $original = $model->getAttributes();
        $model->update($validated);

        if ($request->user()) {
            app(InventoryLogService::class)->logModelUpdated($model, $original, $request->user());
        }

        app(NotificationService::class)->checkAndNotifyLowStock($model);

        return redirect()->back()->with('success', __('inventory.model_updated'));
    }

    /**
     * Remove the specified model from storage along with its stored image.
     */
    public function destroy(Request $request, Model $model)
    {
        if ($model->lots()->exists()) {
            return redirect()->back()->with('error', __('inventory.model_cannot_delete_has_lots'));
        }

        if ($request->user()) {
            app(InventoryLogService::class)->prepareAndLogModelDeleted($model, $request->user());
        }

        $model->delete();

        return redirect()->route('smart.inventory')->with('success', __('inventory.model_deleted'));
    }
}
