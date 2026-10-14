<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockManagement\NewStockRequest;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Master\Category;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Type;
use App\Services\InventoryLogService;
use App\Services\Inventory\AssetNumberService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controller handling dynamic atomic new stock creation (Model, LOT, Asset).
 */
class NewStockController extends Controller
{
    /**
     * Store new stock entry with optional Model, LOT, and Asset layers.
     */
    public function store(NewStockRequest $request)
    {
        $validated = $request->validated();

        $category = Category::findOrFail($validated['category_id']);
        $type = Type::findOrFail($validated['type_id']);
        $isComp = ($category->code === 'COMP' || strtolower($category->name) === 'computer');
        $isKend = ($category->code === 'KEND');

        $result = DB::transaction(function () use ($request, $validated, $category, $type, $isComp, $isKend) {
            // 1. Model Resolution
            if ($request->boolean('create_new_type')) {
                $modelNumber = $validated['model_number'] ?? $this->generateModelNumber($category, $type);
                $model = InventoryModel::create([
                    'number' => $modelNumber,
                    'type_id' => $type->id,
                    'brand_id' => $validated['brand_id'],
                    'uom_id' => $validated['uom_id'],
                    'name' => $validated['model_name'],
                    'specification' => $isComp ? null : ($validated['model_specification'] ?? null),
                    'min_stock_threshold' => (bool)$type->is_consumable ? ($validated['min_stock_threshold'] ?? 0) : null,
                ]);

                if ($request->user()) {
                    app(InventoryLogService::class)->logModelCreated($model, $request->user());
                }
                app(NotificationService::class)->checkAndNotifyLowStock($model);
            } else {
                $model = InventoryModel::with('type.category')->findOrFail($validated['model_id']);
            }

            $lot = null;
            // 2. LOT Resolution
            if ($request->boolean('input_lot')) {
                if ($request->boolean('create_new_lot')) {
                    $entryType = $validated['entry_type'];
                    $rawDate = ($entryType === 'Procurement')
                        ? $validated['purchase_date']
                        : $validated['date_of_grant'];
                    $date = Carbon::parse($rawDate);

                    $lotNumber = $this->generateLotNumber($model, $date);

                    $memoUrl = null;
                    if ($entryType === 'Hibah' && $request->hasFile('memo_file')) {
                        $memoUrl = $request->file('memo_file')->store('memos', 'local');
                    }

                    $lot = Lot::create([
                        'number' => $lotNumber,
                        'model_id' => $model->id,
                        'organizer_id' => $validated['organizer_id'],
                        'vendor_id' => null,
                        'legacy_vendor_id' => null,
                        'initial_quantity' => 0,
                        'current_quantity' => 0,
                        'entry_type' => $entryType,
                        'po_number' => ($entryType === 'Procurement') ? ($validated['po_number'] ?? null) : null,
                        'purchase_grant_date' => $date,
                        'memo_number' => ($entryType === 'Hibah') ? ($validated['memo_number'] ?? null) : null,
                        'memo_url' => $memoUrl,
                        'burden' => $validated['burden'],
                        'project_id' => ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null,
                    ]);

                    if ($request->user()) {
                        app(InventoryLogService::class)->logLotCreated($lot, $request->user());
                    }
                } else {
                    $lot = Lot::with(['model.type.category', 'organizer'])->findOrFail($validated['lot_id']);
                }

                // 3. Asset Resolution
                if ($request->boolean('input_asset') && $lot) {
                    $lot->loadMissing(['model.type.category', 'organizer']);

                    $rawCondition = $validated['condition'];
                    $condition = match ($rawCondition) {
                        'Good' => 'Bagus',
                        'Damaged' => 'Rusak',
                        default => $rawCondition,
                    };

                    $photoUrl = null;
                    if ($request->hasFile('photo')) {
                        $photoUrl = $request->file('photo')->store('assets', 'local');
                    }

                    $assetNumber = app(AssetNumberService::class)->generateAssetNumber($lot);

                    $asset = Asset::create([
                        'number' => $assetNumber,
                        'lot_id' => $lot->id,
                        'location_id' => $validated['location_id'],
                        'status' => 'Belum Diverifikasi',
                        'condition' => $condition,
                        'borrowing_type' => $validated['borrowing_type'],
                        'classification' => $validated['classification'],
                        'specification' => $isComp ? ($validated['asset_specification'] ?? null) : null,
                        'price' => $validated['price'] ?? null,
                        'image_url' => $photoUrl,
                        'vehicle_registration' => $isKend ? ($validated['plate_number'] ?? null) : null,
                        'burden' => $lot->burden ?? 'Corporate',
                        'project_id' => $lot->project_id,
                    ]);

                    $approval = AssetActivationApproval::create([
                        'asset_id' => $asset->id,
                        'requester_id' => $request->user()->id,
                        'decision' => 'pending',
                        'requested_at' => now(),
                    ]);

                    app(NotificationService::class)->notifyManagerNewAssetActivation($asset, $approval);
                }
            }

            return $model;
        });

        return redirect()->back()->with('success', __('inventory.newStockSuccess'));
    }

    /**
     * Generate unique Model Number: {catCode}-{subSuffix}-{0001}
     */
    private function generateModelNumber(Category $category, Type $type): string
    {
        $catCode = trim($category->code);
        $typeCode = trim($type->code);
        $parts = explode('-', $typeCode);
        $subSuffix = end($parts);

        $prefix = "{$catCode}-{$subSuffix}-";
        $existing = InventoryModel::where('number', 'like', "{$prefix}%")->pluck('number');

        $maxNum = 0;
        foreach ($existing as $num) {
            $last = substr($num, strrpos($num, '-') + 1);
            if (is_numeric($last)) {
                $val = (int) $last;
                if ($val > $maxNum) {
                    $maxNum = $val;
                }
            }
        }

        $nextNum = str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT);
        return "{$prefix}{$nextNum}";
    }

    /**
     * Generate unique LOT Number: LOT-{serial:04d}-{yy}-{modelCode}
     */
    private function generateLotNumber(InventoryModel $model, Carbon $date): string
    {
        $yy = $date->format('y');
        $modelCode = $model->number;
        $suffix = "-{$yy}-{$modelCode}";

        $existing = Lot::where('number', 'like', "LOT-%{$suffix}")->pluck('number');

        $maxSerial = 0;
        foreach ($existing as $num) {
            $parts = explode('-', $num);
            if (isset($parts[1]) && is_numeric($parts[1])) {
                $val = (int) $parts[1];
                if ($val > $maxSerial) {
                    $maxSerial = $val;
                }
            }
        }

        $serialStr = str_pad((string) ($maxSerial + 1), 4, '0', STR_PAD_LEFT);
        return "LOT-{$serialStr}{$suffix}";
    }
}
