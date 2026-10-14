<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Master\Brand;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Services\InventoryLogService;
use App\Services\Inventory\AssetNumberService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Lot Controller managing inventory lot batches, procurement tracking, project burden allocation, and lot detail views.
 */
class LotController extends Controller
{
    /**
     * Store a newly created LOT batch in storage.
     */
    public function store(Request $request)
    {
        $modelId = $request->input('model_id', $request->input('barang_id'));
        $request->merge(['model_id' => $modelId]);

        $model = InventoryModel::findOrFail($modelId);
        $isConsumable = (bool) $model->is_consumable;
        $isHibah = ($request->input('entry_type') === 'Hibah');

        $validated = $request->validate([
            'number' => 'required|string|max:50|unique:lots,number',
            'model_id' => 'required|exists:models,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => $isHibah ? 'nullable|integer' : 'required_without:legacy_vendor_id|nullable|integer',
            'legacy_vendor_id' => 'nullable|exists:vendors,id',
            'initial_quantity' => 'nullable|integer|min:0|max:2147483647',
            'current_quantity' => 'nullable|integer|min:0|max:2147483647',
            'po_number' => 'nullable|string|max:255',
            'purchase_grant_date' => 'required|date',
            'entry_type' => 'nullable|string|in:Procurement,Hibah',
            'memo_number' => 'nullable|string|max:255',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => ['required_if:burden,Project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
        ], [
            'vendor_id.required' => 'Vendor wajib dipilih.',
            'vendor_id.required_without' => 'Vendor wajib dipilih.',
            'initial_quantity.required' => 'Jumlah stok awal wajib diisi.',
            'initial_quantity.integer' => 'Tidak boleh desimal.',
            'current_quantity.integer' => 'Tidak boleh desimal.',
            'purchase_grant_date.required' => 'Tanggal pembelian/hibah wajib diisi.',
        ]);

        if ($request->hasFile('memo_file')) {
            $validated['memo_url'] = $request->file('memo_file')->store('memos', 'local');
        }

        if ($isConsumable) {
            $validated['initial_quantity'] = $validated['initial_quantity'] ?? 0;
            $validated['burden'] = $validated['burden'] ?? 'Corporate';
            $validated['project_id'] = ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null;
        } else {
            $validated['initial_quantity'] = null;
            $validated['current_quantity'] = null;
            $validated['burden'] = null;
            $validated['project_id'] = null;
        }
        $validated['vendor_id'] = !empty($validated['vendor_id']) ? (int)$validated['vendor_id'] : null;
        $validated['legacy_vendor_id'] = !empty($validated['legacy_vendor_id']) ? (int)$validated['legacy_vendor_id'] : null;

        $lot = Lot::create($validated);

        if ($request->user()) {
            app(InventoryLogService::class)->logLotCreated($lot, $request->user());
        }

        if ($lot->model) {
            app(NotificationService::class)->checkAndNotifyLowStock($lot->model);
        }

        return redirect()->back()
            ->with('success', __('inventory.lot_created'));
    }

    /**
     * Update the specified LOT batch in storage.
     */
    public function update(Request $request, Lot $lot)
    {
        $isConsumable = (bool) ($lot->model?->is_consumable ?? false);
        $isBeragam = ($lot->vendor_name === 'Beragam' || $lot->vendor?->name === 'Beragam' || $lot->legacyVendor?->name === 'Beragam');

        $modelId = $request->input('model_id', $request->input('barang_id', $lot->model_id));
        $request->merge(['model_id' => $modelId]);

        $isHibah = ($request->input('entry_type', $lot->entry_type) === 'Hibah');
        $vendorRule = ($isBeragam || $isHibah) ? 'nullable|integer' : 'required_without:legacy_vendor_id|nullable|integer';

        $rules = [
            'number' => 'required|string|max:50|unique:lots,number,' . $lot->id,
            'model_id' => 'required|exists:models,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => $vendorRule,
            'legacy_vendor_id' => 'nullable|exists:vendors,id',
            'initial_quantity' => 'nullable|integer|min:0|max:2147483647',
            'po_number' => 'nullable|string|max:255',
            'purchase_grant_date' => 'required|date',
            'entry_type' => 'nullable|string|in:Procurement,Hibah',
            'memo_number' => 'nullable|string|max:255',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => ['required_if:burden,Project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
        ];

        $validated = $request->validate($rules, [
            'vendor_id.required' => 'Vendor wajib dipilih.',
            'vendor_id.required_without' => 'Vendor wajib dipilih.',
            'initial_quantity.integer' => 'Tidak boleh desimal.',
            'purchase_grant_date.required' => 'Tanggal pembelian/hibah wajib diisi.',
        ]);

        if ($request->hasFile('memo_file')) {
            $validated['memo_url'] = $request->file('memo_file')->store('memos', 'local');
        }
        if ($isConsumable) {
            if (!$request->has('initial_quantity')) {
                unset($validated['initial_quantity']);
            }
            $validated['burden'] = $validated['burden'] ?? $lot->burden ?? 'Corporate';
            $validated['project_id'] = ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null;
        } else {
            $validated['initial_quantity'] = null;
            $validated['current_quantity'] = null;
            $validated['burden'] = null;
            $validated['project_id'] = null;
        }

        if ($isBeragam) {
            unset($validated['vendor_id'], $validated['legacy_vendor_id']);
        } else {
            if ($request->has('vendor_id')) {
                $validated['vendor_id'] = !empty($validated['vendor_id']) ? (int)$validated['vendor_id'] : null;
                $validated['legacy_vendor_id'] = $validated['vendor_id'];
            }
        }

        $original = $lot->getAttributes();
        $lot->update($validated);

        if ($request->user()) {
            app(InventoryLogService::class)->logLotUpdated($lot, $original, $request->user());
        }

        if ($lot->model) {
            app(NotificationService::class)->checkAndNotifyLowStock($lot->model);
        }

        return redirect()->back()->with('success', __('inventory.lot_updated'));
    }

    /**
     * Remove the specified LOT batch from storage along with its stored image.
     */
    public function destroy(Request $request, Lot $lot)
    {
        if ($lot->assets()->exists()) {
            return redirect()->back()->with('error', __('inventory.lot_cannot_delete_has_units'));
        }

        if ($request->user()) {
            app(InventoryLogService::class)->prepareAndLogLotDeleted($lot, $request->user());
        }

        $model = $lot->model;
        $modelKey = $model ? ($model->getRouteKey() ?: $model->id) : null;
        $modelUrl = $modelKey ? route('smart.inventory.show', $modelKey) : route('smart.inventory.index');

        $lot->delete();

        if ($model) {
            app(NotificationService::class)->checkAndNotifyLowStock($model);
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', __('inventory.lot_deleted'));
        }

        $previous = url()->previous();
        $lotKeys = array_filter([$lot->id, $lot->number, $lot->getRouteKey()]);
        $isFromLotDetail = $previous && (
            collect($lotKeys)->some(fn($k) => str_contains($previous, '/inventory/lots/' . $k))
            || str_contains($previous, '/inventory/lots/')
        );

        if ($isFromLotDetail) {
            return redirect($modelUrl)->with('success', __('inventory.lot_deleted'));
        }

        return redirect()->back()->with('success', __('inventory.lot_deleted'));
    }

    /**
     * Display detailed LOT batch information in JSON format or render Inertia page.
     */
    public function show(Request $request, Lot $lot)
    {
        $lot->load([
            'model.type.category',
            'model.brand',
            'model.uom',
            'organizer',
            'vendor',
            'legacyVendor',
            'location.parent',
            'project',
        ]);

        $nextAssetNumber = app(AssetNumberService::class)->generateAssetNumber($lot);

        if ($request->wantsJson() && !$request->headers->has('X-Inertia')) {
            return response()->json([
                'id' => $lot->id,
                'number' => $lot->number,
                'model_id' => $lot->model_id,
                'barang_id' => $lot->model_id,
                'po_number' => $lot->po_number,
                'purchase_grant_date' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
                'entry_type' => $lot->entry_type ?? 'Procurement',
                'memo_number' => $lot->memo_number,
                'memo_url' => $lot->memo_url,
                'organizer' => $lot->organizer->name ?? '-',
                'organizer_id' => $lot->organizer_id,
                'vendor' => $lot->vendor_name,
                'vendor_id' => $lot->vendor_id,
                'legacy_vendor_id' => $lot->legacy_vendor_id,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
                'age' => $lot->age,
                
                // Parent model info
                'model_code' => $lot->model->number ?? '-',
                'model_number' => $lot->model->number ?? '-',
                'model_brand' => $lot->model->brand->name ?? '-',
                'model_nama' => $lot->model->name ?? '-',
                'model_name' => $lot->model->name ?? '-',
                'model_specification' => $lot->model->specification ?? '-',
                'model_category' => $lot->model->type->category->name ?? '-',
                'model_category_code' => $lot->model->type->category->code ?? null,
                'model_type' => $lot->model->type->name ?? '-',
                'type_name' => $lot->model->type->name ?? '-',
                'model_uom' => $lot->model->uom->name ?? '-',
                'model_min_stock_threshold' => $lot->model->min_stock_threshold ?? null,

                // Legacy keys
                'barang_code' => $lot->model->number ?? '-',
                'barang_brand' => $lot->model->brand->name ?? '-',
                'barang_nama' => $lot->model->name ?? '-',
                'barang_specification' => $lot->model->specification ?? '-',
                'barang_category' => $lot->model->type->category->name ?? '-',
                'barang_category_code' => $lot->model->type->category->code ?? null,
                'barang_subcategory' => $lot->model->type->name ?? '-',
                'barang_uom' => $lot->model->uom->name ?? '-',
                'barang_min_stock_threshold' => $lot->model->min_stock_threshold ?? null,
                'next_asset_code' => $nextAssetNumber,
                'next_asset_number' => $nextAssetNumber,
            ]);
        }

        // Retrieve asset items associated with this LOT
        $assets = Asset::with([
            'location.parent', 'statusApprovals', 'legacyProject',
            'lot.model.type.category', 'lot.model.brand', 'lot.model.uom',
            'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'legacyVendor', 'lifecycles.actor'
        ])
        ->where('lot_id', $lot->id)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($asset) {
            $pendingApproval = $asset->statusApprovals->firstWhere('decision', 'pending');
            $approvedApproval = $asset->status === 'Tidak Aktif' 
                ? $asset->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;
            $model = $asset->lot->model ?? null;
            return [
                'id' => $asset->id,
                'number' => $asset->number,
                'legacy_number' => $asset->legacy_number,
                'status' => $asset->status,
                'proposed_status' => $pendingApproval 
                    ? $pendingApproval->proposed_condition 
                    : ($approvedApproval ? $approvedApproval->proposed_condition : null),
                'proposed_condition' => $pendingApproval 
                    ? $pendingApproval->proposed_condition 
                    : ($approvedApproval ? $approvedApproval->proposed_condition : null),
                'memo_url' => $pendingApproval 
                    ? $pendingApproval->memo_url 
                    : ($approvedApproval ? $approvedApproval->memo_url : null),
                'lost_doc_url' => $pendingApproval 
                    ? $pendingApproval->lost_doc_url 
                    : ($approvedApproval ? $approvedApproval->lost_doc_url : null),
                'condition' => $asset->condition,
                'type' => $asset->borrowing_type,
                'borrowing_type' => $asset->borrowing_type,
                'classification' => $asset->classification,
                'comp_specification' => $asset->comp_specification,
                'specification' => $asset->comp_specification,
                'price' => $asset->price,
                'image_url' => $asset->image_url,
                'vehicle_registration' => $asset->vehicle_registration,
                'legacy_burden' => $asset->legacy_burden,
                'burden' => $asset->legacy_burden,
                'legacy_project_id' => $asset->legacy_project_id,
                'project_id' => $asset->legacy_project_id,
                'project_name' => $asset->legacyProject ? $asset->legacyProject->project_name : null,
                'project_no' => $asset->legacyProject ? $asset->legacyProject->no_project : null,
                'created_at' => $asset->created_at?->toIso8601String(),
                'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
                
                // Asset-specific legacy vendor
                'asset_vendor' => $asset->legacyVendor?->name,
                'asset_vendor_id' => $asset->legacy_vendor_id,
                'unit_vendor' => $asset->legacyVendor?->name,
                'unit_vendor_id' => $asset->legacy_vendor_id,
                'legacy_vendor_id' => $asset->legacy_vendor_id,

                // Location info
                'location' => $asset->location ? $asset->location->full_name : '-',
                'location_id' => $asset->location_id,

                // Parent lot info
                'lot_id' => $asset->lot_id,
                'lot_number' => $asset->lot->number ?? '-',
                'lot_imageUrl' => $asset->lot->image_url ?? null,
                'lot_unitPrice' => $asset->lot->unit_price ?? null,
                'organizer' => $asset->lot->organizer->name ?? '-',
                'organizer_id' => $asset->lot->organizer_id ?? null,
                'vendor' => $asset->lot?->vendor_name ?? '-',
                'vendor_id' => $asset->lot->vendor_id ?? null,
                'legacy_vendor_id' => $asset->lot->legacy_vendor_id ?? null,
                'lot_organizer' => $asset->lot->organizer->name ?? '-',
                'lot_vendor' => $asset->lot?->vendor_name ?? '-',
                'lot_vendor_id' => $asset->lot->vendor_id ?? null,
                'lot_po_number' => $asset->lot->po_number ?? '-',
                'lot_purchase_grant_date' => ($asset->lot && $asset->lot->purchase_grant_date) ? $asset->lot->purchase_grant_date->format('Y-m-d') : null,
                'lot_age' => $asset->lot->age ?? null,

                // Parent model info
                'model_id' => $model->id ?? null,
                'model_code' => $model->number ?? '-',
                'model_number' => $model->number ?? '-',
                'model_name' => $model->name ?? '-',
                'model_brand' => $model->brand->name ?? '-',
                'model_specification' => $model->specification ?? '-',
                'model_category' => $model->type->category->name ?? '-',
                'model_type' => $model->type->name ?? '-',
                'model_uom' => $model->uom->name ?? '-',

                // Backwards compat with legacy keys
                'barang_id' => $model->id ?? null,
                'barang_code' => $model->number ?? '-',
                'barang_nama' => $model->name ?? '-',
                'barang_brand' => $model->brand->name ?? '-',
                'barang_specification' => $model->specification ?? '-',
                'barang_category' => $model->type->category->name ?? '-',
                'barang_subcategory' => $model->type->name ?? '-',
                'barang_uom' => $model->uom->name ?? '-',

                // Active borrowing info
                'active_borrowing' => $asset->active_borrowing,

                // Audit trails (lifecycles)
                'lifecycles' => $asset->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => $log->actor->name ?? '-',
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ];
        });

        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get(['id', 'name']);
        $locations = Location::with('parent')->active()->orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();
        $users = \App\Models\User::select('id', 'employee_name', 'employee_id')
            ->where('active', 1)
            ->orderBy('employee_name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => "{$u->employee_name} ({$u->employee_id})",
            ]);

        return Inertia::render('Smart/Admin/StockManagement/NonConsumableLotDetail', [
            'lot' => [
                'id' => $lot->id,
                'number' => $lot->number,
                'model_id' => $lot->model_id,
                'barang_id' => $lot->model_id,
                'po_number' => $lot->po_number,
                'purchase_grant_date' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
                'entry_type' => $lot->entry_type ?? 'Procurement',
                'memo_number' => $lot->memo_number,
                'memo_url' => $lot->memo_url,
                'organizer' => $lot->organizer->name ?? '-',
                'organizer_id' => $lot->organizer_id,
                'vendor' => $lot->vendor_name,
                'vendor_id' => $lot->vendor_id,
                'legacy_vendor_id' => $lot->legacy_vendor_id,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
                'age' => $lot->age,
                
                // Parent model info
                'model_code' => $lot->model->number ?? '-',
                'model_number' => $lot->model->number ?? '-',
                'model_brand' => $lot->model->brand->name ?? '-',
                'model_name' => $lot->model->name ?? '-',
                'model_specification' => $lot->model->specification ?? '-',
                'model_category' => $lot->model->type->category->name ?? '-',
                'model_type' => $lot->model->type->name ?? '-',
                'model_uom' => $lot->model->uom->name ?? '-',

                // Backwards compat with legacy keys
                'barang_code' => $lot->model->number ?? '-',
                'barang_brand' => $lot->model->brand->name ?? '-',
                'barang_nama' => $lot->model->name ?? '-',
                'barang_specification' => $lot->model->specification ?? '-',
                'barang_category' => $lot->model->type->category->name ?? '-',
                'barang_subcategory' => $lot->model->type->name ?? '-',
                'barang_uom' => $lot->model->uom->name ?? '-',
                'next_asset_code' => $nextAssetNumber,
                'next_asset_number' => $nextAssetNumber,
            ],
            'assets' => $assets,
            'units' => $assets,
            'brands' => $brands,
            'uoms' => $uoms,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'projects' => $projects,
            'users' => $users,
        ]);
    }
}
