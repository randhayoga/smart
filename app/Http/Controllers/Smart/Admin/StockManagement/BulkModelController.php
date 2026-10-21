<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Services\InventoryLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Bulk Model Controller managing batch updates and deletions for catalog models.
 */
class BulkModelController extends Controller
{
    /**
     * Bulk update multiple catalog models in storage.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:models,id',
            'brand_id' => 'nullable|exists:brands,id',
            'uom_id' => 'nullable|exists:uoms,id',
            'name' => 'nullable|string|max:255',
            'specification' => 'nullable|string|max:255',
            'min_stock_threshold' => 'nullable|integer|min:0',
        ]);

        $updateData = [];

        if ($request->filled('brand_id')) {
            $updateData['brand_id'] = $request->input('brand_id');
        }
        if ($request->filled('uom_id')) {
            $updateData['uom_id'] = $request->input('uom_id');
        }
        if ($request->filled('name')) {
            $updateData['name'] = $request->input('name');
        }
        if ($request->filled('specification')) {
            $updateData['specification'] = $request->input('specification');
        }
        if ($request->filled('min_stock_threshold')) {
            $updateData['min_stock_threshold'] = $request->input('min_stock_threshold');
        }

        if (!empty($updateData)) {
            $models = Model::whereIn('id', $request->input('ids'))->get();
            $originals = [];
            foreach ($models as $m) {
                $originals[$m->id] = $m->getAttributes();
            }

            Model::whereIn('id', $request->input('ids'))->update($updateData);
            $models = Model::whereIn('id', $request->input('ids'))->get();
            foreach ($models as $m) {
                if ($request->user()) {
                    app(InventoryLogService::class)->logModelUpdated($m, $originals[$m->id] ?? [], $request->user());
                }
                app(\App\Services\NotificationService::class)->checkAndNotifyLowStock($m);
            }
        }

        $count = count($request->input('ids'));
        return redirect()->back()->with('success', __('inventory.bulk_model_updated', ['count' => $count]));
    }

    /**
     * Bulk remove multiple catalog models from storage along with their stored images.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:models,id',
        ]);

        $models = Model::whereIn('id', $request->input('ids'))->get();

        $deletedCounter = 0;
        $undeletedCounter = 0;

        foreach ($models as $model) {
            if ($model->lots()->exists()) {
                $undeletedCounter++;
            } else {
                if ($request->user()) {
                    app(InventoryLogService::class)->prepareAndLogModelDeleted($model, $request->user());
                }

                $model->delete();
                $deletedCounter++;
            }
        }

        if ($undeletedCounter > 0) {
            $key = 'error';
            $message = __('inventory.bulk_model_deleted_partial', [
                'deleted' => $deletedCounter,
                'undeleted' => $undeletedCounter,
            ]);
        } else {
            $key = 'success';
            $message = __('inventory.bulk_model_deleted', ['count' => $deletedCounter]);
        }

        return redirect()->back()->with($key, $message);
    }
}
