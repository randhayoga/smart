<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Services\Inventory\StockInventoryQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * StockManagement Controller handling primary inventory catalog listings, item types, and master stock summaries.
 */
class StockManagementController extends Controller
{
    public function __construct(
        private readonly StockInventoryQueryService $queryService
    ) {}

    /**
     * Display the stock inventory overview page.
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('code')->get();
        $types = Type::with('category')->orderBy('code')->get();
        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();

        $models = $this->queryService->getCatalogModels();
        $projects = TbProject::optionsForSelection();
        $organizers = Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']);
        $vendors = Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']);
        $locations = Location::active()->orderBy('name')->get();

        $lots = Lot::query()
            ->toBase()
            ->select(['id', 'number', 'model_id', 'purchase_grant_date', 'organizer_id'])
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => (int) $lot->id,
                    'number' => $lot->number,
                    'model_id' => (int) $lot->model_id,
                    'barang_id' => (int) $lot->model_id,
                    'organizer_id' => $lot->organizer_id ? (int) $lot->organizer_id : null,
                    'purchase_grant_date' => $lot->purchase_grant_date ? Carbon::parse($lot->purchase_grant_date)->format('Y-m-d') : null,
                ];
            });

        return Inertia::render('Smart/Admin/StockManagement/StockManagement', [
            'user' => $request->user(),
            'categories' => $categories,
            'subcategories' => $types,
            'brands' => $brands,
            'uoms' => $uoms,
            'barangs' => $models,
            'projects' => $projects,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'lots' => $lots,
        ]);
    }

    /**
     * Display detailed item information, parent lots, and units.
     */
    public function show(Request $request, InventoryModel $model): Response
    {
        $model->loadMissing(['type.category', 'brand', 'uom']);

        $locations = Location::allWithFullName();
        $locationHierarchyMap = Location::hierarchyMap($locations);

        $projects = TbProject::optionsForSelection();
        $projectNoMap = TbProject::projectNumberMap($projects);

        $lots = Lot::with(['organizer', 'vendor', 'legacyVendor'])
            ->withCount([
                'assets',
                'assets as available_assets_count' => function ($query) {
                    $query->where('status', 'Tersedia');
                },
            ])
            ->where('model_id', $model->id)
            ->get()
            ->map(function ($lot) use ($locationHierarchyMap, $projectNoMap) {
                $locId = $lot->location_id ? (int) $lot->location_id : null;
                $locInfo = ($locId && isset($locationHierarchyMap[$locId])) ? $locationHierarchyMap[$locId] : null;
                $projId = $lot->project_id ? (int) $lot->project_id : null;

                return [
                    'id' => $lot->id,
                    'number' => $lot->number,
                    'model_id' => $lot->model_id,
                    'barang_id' => $lot->model_id,
                    'po_number' => $lot->po_number,
                    'date_of_receipt' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
                    'purchase_grant_date' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
                    'entry_type' => $lot->entry_type ?? 'Procurement',
                    'memo_number' => $lot->memo_number,
                    'memo_url' => $lot->memo_url,
                    'organizer' => $lot->organizer->name ?? '-',
                    'organizer_id' => $lot->organizer_id,
                    'vendor' => $lot->vendor_name,
                    'vendor_id' => $lot->vendor_id,
                    'legacy_vendor_id' => $lot->legacy_vendor_id,
                    'location' => $locInfo ? $locInfo['full_name'] : '-',
                    'location_id' => $locId,
                    'unitPrice' => $lot->unit_price ?? 0,
                    'imageUrl' => $lot->image_url ?: 'inventory/lots/placeholder.jpg',
                    'assetCount' => $lot->assets_count,
                    'availableAssetCount' => $lot->available_assets_count,
                    'initial_quantity' => $lot->initial_quantity,
                    'current_quantity' => $lot->current_quantity,
                    'age' => $lot->age,
                    'burden' => $lot->burden,
                    'project_id' => $projId,
                    'project_no' => ($projId && isset($projectNoMap[$projId])) ? $projectNoMap[$projId] : null,
                    'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
                ];
            });

        $isConsumable = (bool) $model->is_consumable;
        $amount = $isConsumable
            ? (int) $lots->sum('current_quantity')
            : (int) $lots->sum('assetCount');

        $firstLotWithImage = $isConsumable
            ? $lots->first(fn ($l) => !empty($l['imageUrl']) && $l['imageUrl'] !== 'inventory/lots/placeholder.jpg')
            : null;

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
            'initial_stock' => (int) $lots->sum('initial_quantity'),
            'available_stock' => $amount,
            'image_url' => $firstLotWithImage['imageUrl'] ?? null,
            'uom' => $model->uom->name ?? '-',
            'category_id' => $model->type->category_id ?? null,
            'category_code' => $model->type->category->code ?? null,
            'is_consumable' => $isConsumable,
            'brand_id' => $model->brand_id,
            'uom_id' => $model->uom_id,
            'min_stock_threshold' => $model->min_stock_threshold,
        ];

        $units = $isConsumable
            ? collect()
            : $this->queryService->getFlatUnits(
                ['model_id' => $model->id],
                $locationHierarchyMap,
                $projectNoMap
            );

        return Inertia::render('Smart/Admin/StockManagement/ModelDetail', [
            'user' => $request->user(),
            'itemId' => $model->number,
            'barang' => $formattedModel,
            'brands' => Brand::orderBy('name')->get(),
            'uoms' => Uom::orderBy('name')->get(),
            'lots' => $lots,
            'organizers' => Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'locations' => $locations,
            'units' => $units,
            'projects' => $projects,
        ]);
    }
}
