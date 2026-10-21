<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;
use App\Models\TbProject;
use App\Services\ImageUploadService;
use App\Services\InventoryLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Bulk Lot Controller managing batch updates and batch deletions of inventory lots.
 */
class BulkLotController extends Controller
{
    /**
     * Bulk update multiple lot batches in storage.
     */
    public function update(Request $request)
    {
        $rawDate = $request->input('purchase_grant_date', $request->input('purchase_date', $request->input('date_of_receipt')));
        if ($rawDate) {
            $request->merge(['purchase_grant_date' => $rawDate]);
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:lots,id',
            'organizer_id' => 'nullable|exists:organizers,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'purchase_grant_date' => 'nullable|date',
            'burden' => 'nullable|string|in:Corporate,Project,Tidak berubah',
            'project_id' => ['required_if:burden,Project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
            'location_id' => 'nullable|exists:locations,id',
            'unit_price' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024',
        ]);

        $lots = Lot::whereIn('id', $request->input('ids'))->get();
        $imageUploadService = app(ImageUploadService::class);
        $bulkImage = null;
        if ($request->hasFile('image_url')) {
            $bulkImage = $imageUploadService->storeAsWebp($request->file('image_url'), 'inventory');
        }

        foreach ($lots as $lot) {
            $lotData = [];

            if ($request->filled('organizer_id')) {
                $lotData['organizer_id'] = $request->input('organizer_id');
            }
            if ($request->filled('vendor_id')) {
                $lotData['vendor_id'] = $request->input('vendor_id');
            }
            if ($request->filled('purchase_grant_date')) {
                $lotData['purchase_grant_date'] = $request->input('purchase_grant_date');
            }
            if ($lot->model?->is_consumable) {
                if ($request->filled('location_id')) {
                    $lotData['location_id'] = $request->input('location_id');
                }
                if ($request->filled('unit_price')) {
                    $lotData['unit_price'] = $request->input('unit_price');
                }
                if ($bulkImage) {
                    $lotData['image_url'] = $bulkImage;
                }
                if ($request->filled('burden') && $request->input('burden') !== 'Tidak berubah') {
                    $lotData['burden'] = $request->input('burden');
                    $lotData['project_id'] = ($request->input('burden') === 'Project') ? $request->input('project_id') : null;
                }
            } else {
                $lotData['burden'] = null;
                $lotData['project_id'] = null;
                $lotData['location_id'] = null;
                $lotData['unit_price'] = null;
            }

            if (!empty($lotData)) {
                $original = $lot->getAttributes();
                $lot->update($lotData);
                if ($request->user()) {
                    app(InventoryLogService::class)->logLotUpdated($lot, $original, $request->user());
                }
                if ($lot->model) {
                    app(\App\Services\NotificationService::class)->checkAndNotifyLowStock($lot->model);
                }
            }
        }

        $count = count($request->input('ids'));
        return redirect()->back()->with('success', __('inventory.bulk_lot_updated', ['count' => $count]));
    }

    /**
     * Bulk remove multiple lot batches from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:lots,id',
        ]);

        $lots = Lot::whereIn('id', $request->input('ids'))->get();

        $deletedCounter = 0;
        $undeletedCounter = 0;

        foreach ($lots as $lot) {
            if ($lot->assets()->exists()) {
                $undeletedCounter++;
            } else {
                if ($request->user()) {
                    app(InventoryLogService::class)->prepareAndLogLotDeleted($lot, $request->user());
                }

                $lot->delete();
                $deletedCounter++;
            }
        }

        if ($undeletedCounter > 0) {
            $key = 'error';
            $message = __('inventory.bulk_lot_delete_partial_has_units', [
                'deleted' => $deletedCounter,
                'undeleted' => $undeletedCounter
            ]);
        } else {
            $key = 'success';
            $message = __('inventory.bulk_lot_deleted', ['count' => $deletedCounter]);
        }

        return redirect()->back()->with($key, $message);
    }
}
