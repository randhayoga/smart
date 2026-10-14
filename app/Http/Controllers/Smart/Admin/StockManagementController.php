<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock Management Controller providing full catalog overview and single item deep-dive views.
 */
class StockManagementController extends Controller
{
    /**
     * Display the stock inventory overview page.
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('code')->get();
        $types = Type::with('category')->orderBy('code')->get();
        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        
        $models = InventoryModel::with(['type.category', 'brand', 'uom', 'lots.assets'])
            ->get()
            ->map(function ($model) {
                $isConsumable = (bool)$model->is_consumable;
                $amount = $isConsumable 
                    ? (int)$model->lots->sum('current_quantity')
                    : (int)$model->lots->sum(fn($lot) => $lot->assets->count());
                
                return [
                    'id' => $model->id,
                    'code' => $model->number,
                    'number' => $model->number,
                    'category' => $model->type->category->name ?? '-',
                    'type' => $model->type->name ?? '-',
                    'type_id' => $model->type_id,
                    'subcategory' => $model->type->name ?? '-',
                    'subcategory_id' => $model->type_id,
                    'brand' => $model->brand->name ?? '-',
                    'name' => $model->name,
                    'specification' => $model->specification,
                    'lastUpdate' => $model->updated_at ? $model->updated_at->format('d-m-Y H:i') : '-',
                    'amount' => $amount,
                    'initial_stock' => (int)$model->lots->sum('initial_quantity'),
                    'available_stock' => $amount,
                    'image_url' => $model->image_url,
                    'uom' => $model->uom->name ?? '-',
                    'category_id' => $model->type->category_id ?? null,
                    'category_code' => $model->type->category->code ?? null,
                    'is_consumable' => $isConsumable,
                    'brand_id' => $model->brand_id,
                    'uom_id' => $model->uom_id,
                    'min_stock_threshold' => $model->min_stock_threshold,
                ];
            });

        $projects = TbProject::orderBy('project_name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $locations = Location::with('parent')->active()->orderBy('name')->get();
        $lots = Lot::select('id', 'number', 'model_id', 'purchase_grant_date', 'organizer_id')->get()->map(function ($lot) {
            return [
                'id' => $lot->id,
                'number' => $lot->number,
                'model_id' => $lot->model_id,
                'organizer_id' => $lot->organizer_id,
                'purchase_grant_date' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
            ];
        });

        return Inertia::render('Smart/Admin/StockManagement/StockManagement', [
            'user' => $request->user(),
            'categories' => $categories,
            'types' => $types,
            'subcategories' => $types,
            'brands' => $brands,
            'uoms' => $uoms,
            'models' => $models,
            'barangs' => $models,
            'projects' => $projects,
            'organizers' => $organizers,
            'locations' => $locations,
            'lots' => $lots,
        ]);
    }

    /**
     * Display detailed item information, parent lots, and units.
     */
    public function show(Request $request, InventoryModel $model): Response
    {
        $model->loadMissing(['type.category', 'brand', 'uom', 'lots.assets']);

        $isConsumable = (bool)$model->is_consumable;
        $amount = $isConsumable 
            ? (int)$model->lots->sum('current_quantity')
            : (int)$model->lots->sum(fn($lot) => $lot->assets->count());

        $formattedModel = [
            'id' => $model->id,
            'code' => $model->number,
            'number' => $model->number,
            'category' => $model->type->category->name ?? '-',
            'type' => $model->type->name ?? '-',
            'type_id' => $model->type_id,
            'subcategory' => $model->type->name ?? '-',
            'subcategory_id' => $model->type_id,
            'brand' => $model->brand->name ?? '-',
            'name' => $model->name,
            'specification' => $model->specification,
            'lastUpdate' => $model->updated_at ? $model->updated_at->format('d-m-Y H:i') : '-',
            'amount' => $amount,
            'initial_stock' => (int)$model->lots->sum('initial_quantity'),
            'available_stock' => $amount,
            'image_url' => $model->image_url,
            'uom' => $model->uom->name ?? '-',
            'category_id' => $model->type->category_id ?? null,
            'category_code' => $model->type->category->code ?? null,
            'is_consumable' => $isConsumable,
            'brand_id' => $model->brand_id,
            'uom_id' => $model->uom_id,
            'min_stock_threshold' => $model->min_stock_threshold,
        ];

        $lots = Lot::with(['organizer', 'vendor', 'legacyVendor', 'project'])
            ->withCount(['assets', 'assets as available_assets_count' => function ($query) {
                $query->where('status', 'Tersedia');
            }])
            ->where('model_id', $model->id)
            ->get()
            ->map(function ($lot) {
                return [
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
                    'assetCount' => $lot->assets_count,
                    'availableAssetCount' => $lot->available_assets_count,
                    'initial_quantity' => $lot->initial_quantity,
                    'current_quantity' => $lot->current_quantity,
                    'age' => $lot->age,
                    'burden' => $lot->burden,
                    'project_id' => $lot->project_id,
                    'project_name' => $lot->project ? $lot->project->project_name : null,
                    'project_no' => $lot->project ? $lot->project->no_project : null,
                    'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
                ];
            });

        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get(['id', 'name']);
        $locations = Location::with('parent')->active()->orderBy('name')->get();

        $assets = [];
        if (!$isConsumable) {
            $assets = Asset::with([
                'location.parent', 'statusApprovals', 'legacyProject',
                'lot.model.type.category', 'lot.model.brand', 'lot.model.uom',
                'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'legacyVendor', 'lifecycles.actor'
            ])
            ->whereHas('lot', function ($query) use ($model) {
                $query->where('model_id', $model->id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($asset) use ($model) {
                $pendingApproval = $asset->statusApprovals->firstWhere('decision', 'pending');
                $approvedApproval = $asset->status === 'Tidak Aktif' 
                    ? $asset->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                    : null;
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
                    'legacy_memo_number' => $asset->legacy_memo_number,
                    'created_at' => $asset->created_at?->toIso8601String(),
                    'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
                    
                    // Asset-specific legacy vendor
                    'asset_vendor' => $asset->legacyVendor?->name,
                    'asset_vendor_id' => $asset->legacy_vendor_id,
                    'unit_vendor' => $asset->legacyVendor?->name,
                    'unit_vendor_id' => $asset->legacy_vendor_id,
                    'legacy_vendor_id' => $asset->legacy_vendor_id,

                    // Location info
                    'location' => $asset->location?->full_name ?? '-',
                    'location_id' => $asset->location_id,

                    // Parent lot info
                    'lot_id' => $asset->lot_id,
                    'lot_number' => $asset->lot->number ?? '-',
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

                    // Legacy keys
                    'barang_id' => $model->id ?? null,
                    'barang_code' => $model->number ?? '-',
                    'barang_nama' => $model->name ?? '-',
                    'barang_brand' => $model->brand->name ?? '-',
                    'barang_specification' => $model->specification ?? '-',
                    'barang_category' => $model->type->category->name ?? '-',
                    'barang_category_code' => $model->type->category->code ?? null,
                    'barang_subcategory' => $model->type->name ?? '-',
                    'barang_uom' => $model->uom->name ?? '-',

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
        }

        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/StockManagement/ModelDetail', [
            'user' => $request->user(),
            'itemId' => $model->number,
            'model' => $formattedModel,
            'barang' => $formattedModel,
            'brands' => $brands,
            'uoms' => $uoms,
            'lots' => $lots,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'assets' => $assets,
            'units' => $assets,
            'projects' => $projects,
        ]);
    }
}
