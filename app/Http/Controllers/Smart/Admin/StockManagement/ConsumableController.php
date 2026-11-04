<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consumable Controller rendering dedicated consumable stock catalog and batch quantity management (Daftar Habis Pakai).
 */
class ConsumableController extends Controller
{
    public function __construct(
        private readonly StockInventoryQueryService $queryService
    ) {}

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

        $models = $this->queryService->getCatalogModels(true);
        $modelsById = $models->keyBy('id');

        $locations = Location::allWithFullName();
        $locationHierarchyMap = Location::hierarchyMap($locations);

        $projects = TbProject::optionsForSelection();
        $projectNoMap = TbProject::projectNumberMap($projects);

        $lots = Lot::with(['organizer', 'vendor', 'legacyVendor'])
            ->whereHas('model.type', function ($query) {
                $query->where('is_consumable', true);
            })
            ->get()
            ->map(function ($lot) use ($modelsById, $locationHierarchyMap, $projectNoMap) {
                $m = $modelsById->get((int) $lot->model_id);
                $locId = $lot->location_id ? (int) $lot->location_id : null;
                $locInfo = ($locId && isset($locationHierarchyMap[$locId])) ? $locationHierarchyMap[$locId] : null;
                $projId = $lot->project_id ? (int) $lot->project_id : null;

                return [
                    'id' => (int) $lot->id,
                    'number' => $lot->number,
                    'model_id' => (int) $lot->model_id,
                    'barang_id' => (int) $lot->model_id,
                    'po_number' => $lot->po_number,
                    'date_of_receipt' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
                    'purchase_grant_date' => $lot->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
                    'organizer' => $lot->organizer->name ?? '-',
                    'organizer_id' => $lot->organizer_id,
                    'vendor' => $lot->vendor_name,
                    'vendor_id' => $lot->vendor_id,
                    'legacy_vendor_id' => $lot->legacy_vendor_id,
                    'location' => $locInfo ? $locInfo['full_name'] : '-',
                    'location_id' => $locId,
                    'unitPrice' => $lot->unit_price ?? 0,
                    'entry_type' => $lot->entry_type ?? 'Procurement',
                    'memo_number' => $lot->memo_number,
                    'memo_url' => $lot->memo_url,
                    'image_url' => $lot->image_url,
                    'imageUrl' => $lot->image_url,
                    'assetCount' => 0,
                    'availableAssetCount' => 0,
                    'initial_quantity' => $lot->initial_quantity,
                    'current_quantity' => $lot->current_quantity,
                    'age' => $lot->age,
                    'burden' => $lot->burden,
                    'project_id' => $projId,
                    'project_no' => ($projId && isset($projectNoMap[$projId])) ? $projectNoMap[$projId] : null,
                    'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
                    'model_code' => $m['number'] ?? '-',
                    'model_number' => $m['number'] ?? '-',
                    'model_brand' => $m['brand'] ?? '-',
                    'model_name' => $m['name'] ?? '-',
                    'model_specification' => $m['specification'] ?? '-',
                    'model_category' => $m['category'] ?? '-',
                    'model_type' => $m['type'] ?? '-',
                    'model_uom' => $m['uom'] ?? '-',
                    'barang_code' => $m['number'] ?? '-',
                    'barang_brand' => $m['brand'] ?? '-',
                    'barang_nama' => $m['name'] ?? '-',
                    'barang_specification' => $m['specification'] ?? '-',
                    'barang_category' => $m['category'] ?? '-',
                    'barang_subcategory' => $m['type'] ?? '-',
                    'barang_uom' => $m['uom'] ?? '-',
                ];
            });

        return Inertia::render('Smart/Admin/StockManagement/ConsumableList', [
            'user' => $request->user(),
            'categories' => $categories,
            'subcategories' => $types,
            'brands' => $brands,
            'uoms' => $uoms,
            'barangs' => $models,
            'lots' => $lots,
            'organizers' => Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'locations' => $locations,
            'projects' => $projects,
            'selectedBarangCode' => $model ? $model->number : null,
        ]);
    }
}
