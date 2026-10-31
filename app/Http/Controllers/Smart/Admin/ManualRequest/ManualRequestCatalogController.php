<?php

namespace App\Http\Controllers\Smart\Admin\ManualRequest;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * High-performance catalog search controller for the Manual Borrowing/Request modal.
 * Uses flat SQL joins with composite index coverage and zero Eloquent model hydration.
 */
class ManualRequestCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = strtolower(trim((string) $request->input('type', 'consumable')));
        $search = trim((string) $request->input('search', ''));

        if ($type === 'consumable') {
            $models = $this->queryConsumableModels($search);
            return response()->json([
                'items' => $models,
                'models' => $models,
            ]);
        }

        if (in_array($type, ['st', 'lt'], true)) {
            $assets = $this->queryAvailableAssets(strtoupper($type), $search);
            return response()->json([
                'items' => $assets,
                'assets' => $assets,
            ]);
        }

        if ($type === 'rent') {
            $assets = $this->queryAvailableAssets(null, $search);
            return response()->json([
                'items' => $assets,
                'assets' => $assets,
            ]);
        }

        return response()->json(['items' => [], 'models' => [], 'assets' => []]);
    }

    /**
     * Query consumable models with available stock > 0 using flat SQL joins.
     */
    private function queryConsumableModels(string $search): array
    {
        $query = DB::table('models as m')
            ->join('types as t', function ($join) {
                $join->on('t.id', '=', 'm.type_id')
                    ->where('t.is_consumable', true);
            })
            ->join('categories as c', 'c.id', '=', 't.category_id')
            ->join('brands as b', 'b.id', '=', 'm.brand_id')
            ->join('uoms as u', 'u.id', '=', 'm.uom_id')
            ->join('lots as l', function ($join) {
                $join->on('l.model_id', '=', 'm.id')
                    ->where('l.current_quantity', '>', 0);
            })
            ->select([
                'm.id',
                'm.number as code',
                'm.name',
                'm.specification',
                'm.type_id',
                't.name as type',
                'c.name as category',
                'c.code as category_code',
                'b.name as brand',
                'u.name as uom',
                DB::raw('SUM(l.current_quantity) as available_stock'),
                DB::raw('MAX(l.image_url) as image_url'),
            ])
            ->groupBy(
                'm.id',
                'm.number',
                'm.name',
                'm.specification',
                'm.type_id',
                't.name',
                'c.name',
                'c.code',
                'b.name',
                'u.name'
            )
            ->havingRaw('SUM(l.current_quantity) > 0')
            ->orderBy('m.name');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('m.name', 'like', $like)
                    ->orWhere('m.specification', 'like', $like)
                    ->orWhere('m.number', 'like', $like)
                    ->orWhere('b.name', 'like', $like)
                    ->orWhere('t.name', 'like', $like);
            });
        }

        return $query->get()->map(fn($row) => [
            'id' => (int) $row->id,
            'number' => $row->code,
            'code' => $row->code,
            'name' => $row->name,
            'specification' => $row->specification ?? '',
            'type_id' => (int) $row->type_id,
            'type' => $row->type,
            'type_name' => $row->type,
            'category' => $row->category,
            'category_name' => $row->category,
            'category_code' => $row->category_code,
            'brand' => $row->brand,
            'brand_name' => $row->brand,
            'uom' => $row->uom,
            'uom_name' => $row->uom,
            'available_stock' => (int) $row->available_stock,
            'image_url' => $row->image_url ? '/media/' . ltrim($row->image_url, '/') : null,
        ])->values()->all();
    }

    /**
     * Query available non-consumable assets for ST, LT, or rent (both ST & LT) with null approval_status.
     */
    private function queryAvailableAssets(?string $borrowingType, string $search): array
    {
        $query = DB::table('assets as a')
            ->join('lots as l', 'l.id', '=', 'a.lot_id')
            ->join('models as m', 'm.id', '=', 'l.model_id')
            ->join('types as t', function ($join) {
                $join->on('t.id', '=', 'm.type_id')
                    ->where('t.is_consumable', false);
            })
            ->join('categories as c', 'c.id', '=', 't.category_id')
            ->join('brands as b', 'b.id', '=', 'm.brand_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'm.uom_id')
            ->leftJoin('locations as loc', 'loc.id', '=', 'a.location_id')
            ->leftJoin('locations as ploc', 'ploc.id', '=', 'loc.parent_id')
            ->leftJoin('locations as gploc', 'gploc.id', '=', 'ploc.parent_id')
            ->when($borrowingType !== null, function ($q) use ($borrowingType) {
                $q->where('a.borrowing_type', $borrowingType);
            })
            ->where('a.status', 'Tersedia')
            ->whereNull('a.approval_status')
            ->select([
                'a.id',
                'a.number as asset_number',
                'a.comp_specification',
                'a.condition',
                'a.borrowing_type',
                'a.image_url as asset_image_url',
                'l.image_url as lot_image_url',
                'm.id as model_id',
                'm.number as model_code',
                'm.name as model_name',
                'm.specification as model_specification',
                'm.type_id',
                't.name as type',
                'c.name as category',
                'c.code as category_code',
                'b.name as brand',
                'u.name as uom',
                'loc.name as loc_name',
                'ploc.name as parent_loc_name',
                'gploc.name as grandparent_loc_name',
            ])
            ->orderBy('a.number');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('a.number', 'like', $like)
                    ->orWhere('a.comp_specification', 'like', $like)
                    ->orWhere('m.name', 'like', $like)
                    ->orWhere('m.specification', 'like', $like)
                    ->orWhere('b.name', 'like', $like)
                    ->orWhere('t.name', 'like', $like);
            });
        }

        return $query->get()->map(function ($row) {
            $locParts = array_values(array_filter([
                $row->grandparent_loc_name,
                $row->parent_loc_name,
                $row->loc_name,
            ]));
            $imagePath = $row->asset_image_url ?: $row->lot_image_url;
            $spec = trim((string) ($row->comp_specification ?: $row->model_specification));
            $assetNum = trim((string) $row->asset_number);
            $locationName = !empty($locParts) ? implode(' - ', $locParts) : '-';

            return [
                'id' => (int) $row->id,
                'number' => $assetNum,
                'asset_number' => $assetNum,
                'model_id' => (int) $row->model_id,
                'model_code' => $row->model_code,
                'model_number' => $row->model_code,
                'type_id' => (int) $row->type_id,
                'name' => $row->model_name,
                'model_name' => $row->model_name,
                'specification' => $spec,
                'comp_specification' => $row->comp_specification ?? '',
                'model_specification' => $row->model_specification ?? '',
                'brand' => $row->brand,
                'brand_name' => $row->brand,
                'type' => $row->type,
                'type_name' => $row->type,
                'category' => $row->category,
                'category_name' => $row->category,
                'category_code' => $row->category_code,
                'uom' => $row->uom ?? 'Unit',
                'uom_name' => $row->uom ?? 'Unit',
                'condition' => $row->condition,
                'borrowing_type' => $row->borrowing_type,
                'location' => $locationName,
                'location_name' => $locationName,
                'image_url' => $imagePath ? '/media/' . ltrim($imagePath, '/') : null,
            ];
        })->values()->all();
    }
}
