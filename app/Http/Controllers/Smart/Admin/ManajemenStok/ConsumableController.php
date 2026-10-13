<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
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
 * Consumable Controller rendering dedicated consumable stock catalog and batch quantity management (Daftar Habis Pakai).
 */
class ConsumableController extends Controller
{
    /**
     * Display the consumable listing page (Daftar Habis Pakai).
     */
    public function index(Request $request, ?InventoryModel $model = null): Response
    {
        if (!$model) {
            $modelParam = $request->route('model') ?? $request->route('barang') ?? $request->query('model') ?? $request->query('barang');
            if ($modelParam) {
                $model = (new InventoryModel)->resolveRouteBinding($modelParam);
            } elseif ($request->filled('model_id')) {
                $model = InventoryModel::find($request->query('model_id'));
            } elseif ($request->filled('barang_id')) {
                $model = InventoryModel::find($request->query('barang_id'));
            }
        }

        $categories = Category::whereHas('types', function ($query) {
            $query->where('is_consumable', true);
        })->orderBy('code')->get();
        $types = Type::where('is_consumable', true)->with('category')->orderBy('code')->get();
        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();

        $models = InventoryModel::with(['type.category', 'brand', 'uom', 'lots'])
            ->whereHas('type', function ($query) {
                $query->where('is_consumable', true);
            })
            ->get()
            ->map(function ($m) {
                $amount = (int) $m->lots->sum('current_quantity');
                return [
                    'id' => (int) $m->id,
                    'code' => $m->number,
                    'number' => $m->number,
                    'category' => $m->type->category->name ?? '-',
                    'type' => $m->type->name ?? '-',
                    'type_id' => $m->type_id,
                    'subcategory' => $m->type->name ?? '-',
                    'subcategory_id' => $m->type_id,
                    'category_id' => $m->type->category_id ?? null,
                    'brand' => $m->brand->name ?? '-',
                    'name' => $m->name,
                    'specification' => $m->specification,
                    'lastUpdate' => $m->updated_at ? $m->updated_at->format('d-m-Y H:i') : '-',
                    'amount' => $amount,
                    'initial_stock' => (int) $m->lots->sum('initial_quantity'),
                    'available_stock' => $amount,
                    'image_url' => $m->image_url,
                    'uom' => $m->uom->name ?? '-',
                    'is_consumable' => true,
                    'brand_id' => $m->brand_id,
                    'uom_id' => $m->uom_id,
                    'min_stock_threshold' => $m->min_stock_threshold,
                ];
            });

        $lots = Lot::with([
            'model.type.category',
            'model.brand',
            'model.uom',
            'organizer',
            'vendor',
            'legacyVendor',
            'location.parent',
            'project',
        ])
        ->whereHas('model.type', function ($query) {
            $query->where('is_consumable', true);
        })
        ->get()
        ->map(function ($lot) {
            return [
                'id' => (int) $lot->id,
                'number' => $lot->number,
                'model_id' => (int) $lot->model_id,
                'barang_id' => (int) $lot->model_id,
                'po_number' => $lot->po_number,
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('Y-m-d') : null,
                'organizer' => $lot->organizer->name ?? '-',
                'organizer_id' => $lot->organizer_id,
                'vendor' => $lot->vendor_name,
                'vendor_id' => $lot->vendor_id,
                'legacy_vendor_id' => $lot->legacy_vendor_id,
                'location' => $lot->location ? $lot->location->full_name : '-',
                'location_id' => $lot->location_id,
                'unitPrice' => $lot->unit_price,
                'imageUrl' => $lot->image_url,
                'assetCount' => 0,
                'availableAssetCount' => 0,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'age' => $lot->age,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
                
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
            ];
        });

        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get(['id', 'name']);
        $locations = Location::with('parent')->active()->orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DaftarHabisPakai', [
            'user' => $request->user(),
            'categories' => $categories,
            'types' => $types,
            'subcategories' => $types,
            'brands' => $brands,
            'uoms' => $uoms,
            'models' => $models,
            'barangs' => $models,
            'lots' => $lots,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'projects' => $projects,
            'selectedModelNumber' => $model?->number ?? null,
            'selectedBarangCode' => $model?->number ?? null,
        ]);
    }
}
