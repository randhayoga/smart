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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Fetch scoped inventory audit logs ONLY if a specific consumable model is selected
        $inventoryLogs = collect();
        if ($model) {
            $logRows = DB::table('inventory_logs as il')
                ->leftJoin('models as m', 'm.id', '=', 'il.model_id')
                ->leftJoin('lots as l', 'l.id', '=', 'il.lot_id')
                ->leftJoin('models as lm', 'lm.id', '=', 'l.model_id')
                ->where(function ($q) use ($model) {
                    $q->where('il.model_id', $model->id)
                      ->orWhere('l.model_id', $model->id);
                })
                ->select([
                    'il.id',
                    'il.user_id',
                    'il.action_type',
                    'il.quantity_change',
                    'il.previous_state',
                    'il.note',
                    'il.created_at',
                    'm.number as model_number',
                    'm.name as model_name',
                    'l.number as lot_number',
                    'lm.number as lot_model_number',
                    'lm.name as lot_model_name',
                ])
                ->orderByDesc('il.created_at')
                ->orderByDesc('il.id')
                ->get();

            $userIds = $logRows->pluck('user_id')->filter()->unique()->values()->all();
            $userNames = !empty($userIds)
                ? DB::connection('user_hris')
                    ->table('hrd_employee')
                    ->whereIn('id', $userIds)
                    ->pluck('employee_name', 'id')
                    ->all()
                : [];

            $inventoryLogs = $logRows->map(function ($row) use ($userNames) {
                $prevState = is_string($row->previous_state)
                    ? (json_decode($row->previous_state, true) ?? [])
                    : (is_array($row->previous_state) ? $row->previous_state : []);

                $barangNumber = $row->model_number
                    ?? $row->lot_model_number
                    ?? $prevState['barang_number']
                    ?? $prevState['number']
                    ?? '-';
                $barangName = $row->model_name
                    ?? $row->lot_model_name
                    ?? $prevState['barang_name']
                    ?? $prevState['name']
                    ?? '-';
                $lotNumber = $row->lot_number
                    ?? $prevState['lot_number']
                    ?? '-';

                $actor = ($row->user_id && isset($userNames[(int) $row->user_id]))
                    ? $userNames[(int) $row->user_id]
                    : ($prevState['actor_name'] ?? ($row->user_id ? "User #{$row->user_id}" : '-'));

                $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;

                return [
                    'id' => (int) $row->id,
                    'barang_number' => $barangNumber,
                    'barang_name' => $barangName,
                    'lot_number' => $lotNumber,
                    'action_type' => $row->action_type ?? '-',
                    'quantity_change' => (int) $row->quantity_change,
                    'actor' => $actor,
                    'note' => $row->note ?? '-',
                    'waktu' => $createdAt ? $createdAt->format('d-m-Y H:i:s') : '-',
                    'created_at_raw' => $createdAt ? $createdAt->toISOString() : null,
                ];
            });
        }

        return Inertia::render('Smart/Admin/StockManagement/ConsumableList', [
            'user' => $request->user(),
            'categories' => $categories,
            'subcategories' => $types,
            'brands' => $brands,
            'uoms' => $uoms,
            'barangs' => $models,
            'lots' => $lots,
            'inventoryLogs' => $inventoryLogs,
            'organizers' => Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'locations' => $locations,
            'projects' => $projects,
            'selectedBarangCode' => $model ? $model->number : null,
        ]);
    }
}
