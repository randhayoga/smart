<?php

namespace App\Services\Inventory;

use App\Models\Master\Location;
use App\Models\TbProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * High-performance flat SQL query service for Stock Management catalog and asset datatables.
 * Avoids hydrating thousands of nested Eloquent models and eliminates N+1 serialization bottlenecks.
 */
class StockInventoryQueryService
{
    /**
     * Fetch catalog models (barangs) with aggregated stock counts in a single SQL query.
     *
     * @param bool|null $onlyConsumable Filter to only consumable (true), non-consumable (false), or all (null)
     */
    public function getCatalogModels(?bool $onlyConsumable = null): Collection
    {
        $lotAssetSubquery = DB::table('lots as l')
            ->leftJoin('assets as a', 'a.lot_id', '=', 'l.id')
            ->selectRaw("
                l.model_id,
                SUM(COALESCE(l.current_quantity, 0)) as total_current_qty,
                SUM(COALESCE(l.initial_quantity, 0)) as total_initial_qty,
                COUNT(a.id) as total_assets,
                MAX(CASE WHEN l.image_url IS NOT NULL AND l.image_url != '' THEN l.image_url ELSE NULL END) as lot_image_url
            ")
            ->groupBy('l.model_id');

        $lotQtySubquery = DB::table('lots')
            ->selectRaw("
                model_id,
                SUM(COALESCE(current_quantity, 0)) as sum_current_quantity,
                SUM(COALESCE(initial_quantity, 0)) as sum_initial_quantity,
                MAX(CASE WHEN image_url IS NOT NULL AND image_url != '' THEN image_url ELSE NULL END) as first_image_url
            ")
            ->groupBy('model_id');

        $assetCountSubquery = DB::table('lots as l')
            ->join('assets as a', 'a.lot_id', '=', 'l.id')
            ->selectRaw('l.model_id, COUNT(a.id) as asset_count')
            ->groupBy('l.model_id');

        $query = DB::table('models as m')
            ->leftJoin('types as t', 't.id', '=', 'm.type_id')
            ->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'm.brand_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'm.uom_id')
            ->leftJoinSub($lotQtySubquery, 'lq', 'lq.model_id', '=', 'm.id')
            ->leftJoinSub($assetCountSubquery, 'ac', 'ac.model_id', '=', 'm.id')
            ->select([
                'm.id',
                'm.number',
                'm.type_id',
                'm.brand_id',
                'm.uom_id',
                'm.name',
                'm.specification',
                'm.min_stock_threshold',
                'm.updated_at',
                't.name as type_name',
                't.code as type_code',
                't.is_consumable',
                't.category_id',
                'c.name as category_name',
                'c.code as category_code',
                'b.name as brand_name',
                'u.name as uom_name',
                'lq.sum_current_quantity',
                'lq.sum_initial_quantity',
                'lq.first_image_url',
                'ac.asset_count',
            ]);

        if ($onlyConsumable !== null) {
            $query->where('t.is_consumable', $onlyConsumable);
        }

        return $query->get()->map(function ($row) {
            $isConsumable = (bool) ($row->is_consumable ?? false);
            $amount = $isConsumable
                ? (int) ($row->sum_current_quantity ?? 0)
                : (int) ($row->asset_count ?? 0);
            $initialStock = (int) ($row->sum_initial_quantity ?? 0);

            return [
                'id' => (int) $row->id,
                'code' => $row->number,
                'number' => $row->number,
                'category' => $row->category_name ?? '-',
                'type' => $row->type_name ?? '-',
                'type_id' => $row->type_id ? (int) $row->type_id : null,
                'subcategory' => $row->type_name ?? '-',
                'subcategory_id' => $row->type_id ? (int) $row->type_id : null,
                'subcategory_code' => $row->type_code ?? '-',
                'brand' => $row->brand_name ?? '-',
                'name' => $row->name,
                'specification' => $row->specification,
                'lastUpdate' => $row->updated_at ? date('d-m-Y H:i', strtotime((string) $row->updated_at)) : '-',
                'amount' => $amount,
                'initial_stock' => $initialStock,
                'available_stock' => $amount,
                'image_url' => $isConsumable ? ($row->first_image_url ?? null) : null,
                'uom' => $row->uom_name ?? '-',
                'category_id' => $row->category_id ? (int) $row->category_id : null,
                'category_code' => $row->category_code ?? null,
                'is_consumable' => $isConsumable,
                'brand_id' => $row->brand_id ? (int) $row->brand_id : null,
                'uom_id' => $row->uom_id ? (int) $row->uom_id : null,
                'min_stock_threshold' => $row->min_stock_threshold !== null ? (int) $row->min_stock_threshold : 0,
            ];
        });
    }

    /**
     * Fetch flat asset/unit rows for datatables using a single SQL query and in-memory hierarchy maps.
     *
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>>|null $locationHierarchyMap
     * @param array<int, string>|null $projectNoMap
     */
    public function getFlatUnits(
        array $filters = [],
        ?array $locationHierarchyMap = null,
        ?array $projectNoMap = null
    ): Collection {
        $locationHierarchyMap = $locationHierarchyMap ?? Location::hierarchyMap();
        $projectNoMap = $projectNoMap ?? TbProject::projectNumberMap();

        // 1. Load Lot + Model + Type + Category + Brand + Uom + Organizer + Vendor dictionary (663 rows vs 6,515 rows over PDO)
        $lotQuery = DB::table('lots as l')
            ->leftJoin('models as m', 'm.id', '=', 'l.model_id')
            ->leftJoin('types as t', 't.id', '=', 'm.type_id')
            ->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'm.brand_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'm.uom_id')
            ->leftJoin('organizers as o', 'o.id', '=', 'l.organizer_id')
            ->leftJoin('vendors as lv', 'lv.id', '=', 'l.vendor_id')
            ->leftJoin('vendors as llv', 'llv.id', '=', 'l.legacy_vendor_id')
            ->select([
                'l.id as lot_id',
                'l.number as lot_number',
                'l.organizer_id',
                'l.vendor_id as lot_vendor_id',
                'l.legacy_vendor_id as lot_legacy_vendor_id',
                'l.unit_price as lot_unit_price',
                'l.image_url as lot_image_url',
                'l.po_number as lot_po_number',
                'l.purchase_grant_date as lot_purchase_grant_date',
                'l.burden as lot_burden',
                'l.project_id as lot_project_id',
                'm.id as model_id',
                'm.number as model_number',
                'm.name as model_name',
                'm.specification as model_specification',
                't.name as type_name',
                'c.name as category_name',
                'c.code as category_code',
                'b.name as brand_name',
                'u.name as uom_name',
                'o.name as organizer_name',
                'lv.name as lot_vendor_name',
                'llv.name as lot_legacy_vendor_name',
            ]);

        if (!empty($filters['model_id'])) {
            $lotQuery->where('l.model_id', (int) $filters['model_id']);
        }
        if (!empty($filters['lot_id'])) {
            $lotQuery->where('l.id', (int) $filters['lot_id']);
        }

        $lotsById = [];
        foreach ($lotQuery->get() as $lRow) {
            $catCode = strtoupper((string) ($lRow->category_code ?? ''));
            $catName = strtolower((string) ($lRow->category_name ?? ''));
            $lRow->is_comp_category = $catCode === 'COMP' || in_array($catName, ['computer', 'komputer'], true);
            $lRow->is_sw_category = $catCode === 'SW' || $catName === 'software' || str_contains($catName, 'perangkat lunak');
            $lRow->formatted_grant_date = $lRow->lot_purchase_grant_date ? substr((string) $lRow->lot_purchase_grant_date, 0, 10) : null;
            $lotsById[(int) $lRow->lot_id] = $lRow;
        }

        if ((!empty($filters['model_id']) || !empty($filters['lot_id'])) && empty($lotsById)) {
            return collect();
        }

        // 2. Load sparse deletion approvals map and vendor names map
        $vendorsById = DB::table('vendors')->pluck('name', 'id')->all();

        $latestDaByAsset = [];
        $approvedDaByAsset = [];
        $daRows = DB::table('asset_deletion_approvals')
            ->select([
                'id',
                'asset_id',
                'decision',
                'proposed_condition',
                'memo_url',
                'lost_doc_url',
                'bod_boc_approval_url',
                'bod_boc_disapproval_url',
                'admin_note',
                'dm_note',
            ])
            ->orderByDesc('id')
            ->get();

        foreach ($daRows as $da) {
            $aid = (int) $da->asset_id;
            if (!isset($latestDaByAsset[$aid])) {
                $latestDaByAsset[$aid] = $da;
            }
            if ($da->decision === 'approved' && !isset($approvedDaByAsset[$aid])) {
                $approvedDaByAsset[$aid] = $da;
            }
        }

        // 3. Load compact assets table rows
        $query = DB::table('assets as a')->select([
            'a.id',
            'a.number',
            'a.legacy_number',
            'a.lot_id',
            'a.location_id',
            'a.status',
            'a.approval_status',
            'a.condition',
            'a.borrowing_type',
            'a.classification',
            'a.comp_specification',
            'a.price',
            'a.image_url',
            'a.vehicle_registration',
            'a.is_assigned_to_location',
            'a.license_exp_date',
            'a.renewal_reminder',
            'a.burden',
            'a.project_id',
            'a.legacy_vendor_id as asset_legacy_vendor_id',
            'a.qc_pass',
            'a.last_qc',
            'a.created_at',
            'a.updated_at',
        ]);

        if (!empty($filters['exclude_unverified'])) {
            $query->where('a.status', '!=', 'Belum Diverifikasi')
                ->where('a.status', 'not like', '%,Belum Diverifikasi%')
                ->where('a.status', 'not like', 'Belum Diverifikasi,%');
        }

        if (!empty($filters['only_unverified'])) {
            $query->where(function ($q) {
                $q->where('a.status', 'Belum Diverifikasi')
                    ->orWhere('a.status', 'like', '%,Belum Diverifikasi%')
                    ->orWhere('a.status', 'like', 'Belum Diverifikasi,%');
            });
        }

        if (!empty($filters['approval_statuses']) && is_array($filters['approval_statuses'])) {
            $statuses = $filters['approval_statuses'];
            $query->where(function ($q) use ($statuses) {
                $q->whereIn('a.approval_status', $statuses);
                foreach ($statuses as $st) {
                    $q->orWhere('a.status', 'like', "%{$st}%");
                }
            });
        }

        if (!empty($filters['model_id'])) {
            $query->whereIn('a.lot_id', array_keys($lotsById));
        }

        if (!empty($filters['lot_id'])) {
            $query->where('a.lot_id', (int) $filters['lot_id']);
        }

        $orderBy = $filters['order_by'] ?? 'created_at_desc';
        if ($orderBy === 'number_asc') {
            $query->orderBy('a.number');
        } else {
            $query->orderByDesc('a.created_at')->orderByDesc('a.id');
        }

        $rows = $query->get();

        return $rows->map(function ($row) use ($lotsById, $vendorsById, $latestDaByAsset, $approvedDaByAsset, $locationHierarchyMap, $projectNoMap) {
            $aid = (int) $row->id;
            $lotInfo = isset($lotsById[(int) $row->lot_id]) ? $lotsById[(int) $row->lot_id] : null;
            $locId = $row->location_id ? (int) $row->location_id : null;
            $locInfo = ($locId && isset($locationHierarchyMap[$locId])) ? $locationHierarchyMap[$locId] : null;

            $isCompCategory = $lotInfo ? $lotInfo->is_comp_category : false;
            $isSwCategory = $lotInfo ? $lotInfo->is_sw_category : false;
            $modelSpec = $lotInfo->model_specification ?? null;
            $specValue = $isCompCategory ? ($row->comp_specification ?? null) : $modelSpec;

            $assetLegacyVendorId = $row->asset_legacy_vendor_id ? (int) $row->asset_legacy_vendor_id : null;
            $assetLegacyVendorName = ($assetLegacyVendorId && isset($vendorsById[$assetLegacyVendorId])) ? $vendorsById[$assetLegacyVendorId] : null;

            $effectiveVendorId = $lotInfo?->lot_vendor_id ?? $assetLegacyVendorId ?? $lotInfo?->lot_legacy_vendor_id;
            $effectiveLegacyVendorId = $assetLegacyVendorId ?? $lotInfo?->lot_legacy_vendor_id;
            $effectiveVendorName = $lotInfo?->lot_vendor_name
                ?? $assetLegacyVendorName
                ?? $lotInfo?->lot_legacy_vendor_name
                ?? '-';

            $latestDa = $latestDaByAsset[$aid] ?? null;
            $approvedDa = $approvedDaByAsset[$aid] ?? null;
            $hasPendingDa = ($latestDa?->decision ?? null) === 'pending';
            $proposedCondition = $hasPendingDa ? ($latestDa->proposed_condition ?? null) : ($approvedDa->proposed_condition ?? null);

            $memoUrl = $hasPendingDa ? ($latestDa->memo_url ?: $approvedDa?->memo_url) : ($approvedDa?->memo_url ?: $latestDa?->memo_url);
            $lostDocUrl = $hasPendingDa ? ($latestDa->lost_doc_url ?: $approvedDa?->lost_doc_url) : ($approvedDa?->lost_doc_url ?: $latestDa?->lost_doc_url);
            $bodApprovalUrl = $hasPendingDa ? ($latestDa->bod_boc_approval_url ?: $approvedDa?->bod_boc_approval_url) : ($approvedDa?->bod_boc_approval_url ?: $latestDa?->bod_boc_approval_url);
            $bodDisapprovalUrl = $hasPendingDa ? ($latestDa->bod_boc_disapproval_url ?: $approvedDa?->bod_boc_disapproval_url) : ($approvedDa?->bod_boc_disapproval_url ?: $latestDa?->bod_boc_disapproval_url);
            $adminNote = $hasPendingDa ? ($latestDa->admin_note ?? $approvedDa?->admin_note) : ($approvedDa?->admin_note ?? $latestDa?->admin_note);
            $dmNote = $hasPendingDa ? ($latestDa->dm_note ?? $approvedDa?->dm_note) : ($approvedDa?->dm_note ?? $latestDa?->dm_note);

            $projectId = $row->project_id ?? $lotInfo?->lot_project_id;
            $projectNo = ($projectId && isset($projectNoMap[(int) $projectId])) ? $projectNoMap[(int) $projectId] : null;

            $imageUrl = $row->image_url ?: ($lotInfo?->lot_image_url ?: 'inventory/lots/placeholder.jpg');
            $price = $row->price ?? ($lotInfo?->lot_unit_price ?? 0);
            $borrowingType = $row->borrowing_type ?: 'LT';

            // Fast string-based date formatting ('Y-m-d H:i:s' -> 'd-m-Y H:i') without strtotime() overhead on 6,500+ rows
            $uStr = (string) ($row->updated_at ?? '');
            $updatedFormatted = strlen($uStr) >= 16
                ? substr($uStr, 8, 2) . '-' . substr($uStr, 5, 2) . '-' . substr($uStr, 0, 4) . ' ' . substr($uStr, 11, 5)
                : '-';

            $item = [
                'id' => $aid,
                'number' => $row->number,
                'legacy_number' => $row->legacy_number,
                'status' => $row->status,
                'approval_status' => $row->approval_status,
                'condition' => $row->condition,
                'qc_pass' => $row->qc_pass !== null ? (bool) $row->qc_pass : null,
                'last_qc' => $row->last_qc ? substr((string) $row->last_qc, 0, 10) : null,
                'type' => $borrowingType,
                'borrowing_type' => $borrowingType,
                'classification' => $row->classification ?? 'Aset',
                'comp_specification' => $specValue,
                'specification' => $specValue,
                'location' => $locInfo ? $locInfo['full_name'] : '-',
                'location_id' => $locId,
                'price' => $price,
                'image_url' => $imageUrl,
                'is_assigned_to_location' => (bool) ($row->is_assigned_to_location ?? false),
                'is_software' => $isSwCategory,
                'burden' => $row->burden ?? $lotInfo?->lot_burden ?? 'Corporate',
                'vendor_id' => $effectiveVendorId ? (int) $effectiveVendorId : null,
                'vendor' => $effectiveVendorName,
                'lot_vendor' => $effectiveVendorName,
                'organizer_id' => $lotInfo?->organizer_id ? (int) $lotInfo->organizer_id : null,
                'organizer' => $lotInfo?->organizer_name ?? '-',
                'lot_organizer' => $lotInfo?->organizer_name ?? '-',
                'created_at' => $row->created_at ? str_replace(' ', 'T', substr((string) $row->created_at, 0, 19)) . 'Z' : null,
                'updated_at' => $updatedFormatted,
                'barang_id' => $lotInfo?->model_id ? (int) $lotInfo->model_id : null,
                'barang_code' => $lotInfo?->model_number ?? '-',
                'barang_brand' => $lotInfo?->brand_name ?? '-',
                'barang_nama' => $lotInfo?->model_name ?? '-',
                'barang_specification' => $modelSpec ?? '-',
                'barang_category' => $lotInfo?->category_name ?? '-',
                'barang_category_code' => $lotInfo?->category_code ?? null,
                'barang_subcategory' => $lotInfo?->type_name ?? '-',
                'barang_uom' => $lotInfo?->uom_name ?? '-',
                'lot_id' => $row->lot_id ? (int) $row->lot_id : null,
                'lot_number' => $lotInfo?->lot_number ?? '-',
                'lot_po_number' => $lotInfo?->lot_po_number ?? '-',
            ];

            // Only attach optional/sparse fields when non-null to keep JSON payload compact across 6,500+ rows
            $optionalFields = [
                'proposed_status' => $proposedCondition,
                'proposed_condition' => $proposedCondition,
                'memo_url' => $memoUrl,
                'lost_doc_url' => $lostDocUrl,
                'bod_boc_approval_url' => $bodApprovalUrl,
                'bod_boc_disapproval_url' => $bodDisapprovalUrl,
                'admin_note' => $adminNote,
                'dm_note' => $dmNote,
                'vehicle_registration' => $row->vehicle_registration,
                'license_exp_date' => $row->license_exp_date ? substr((string) $row->license_exp_date, 0, 10) : null,
                'renewal_reminder' => $row->renewal_reminder !== null ? (int) $row->renewal_reminder : null,
                'project_id' => $projectId ? (int) $projectId : null,
                'project_no' => $projectNo,
                'asset_vendor' => $assetLegacyVendorName,
                'asset_vendor_id' => $assetLegacyVendorId,
                'legacy_vendor_id' => $effectiveLegacyVendorId ? (int) $effectiveLegacyVendorId : null,
                'lot_vendor_id' => $lotInfo?->lot_vendor_id ? (int) $lotInfo->lot_vendor_id : null,
                'lot_imageUrl' => $lotInfo?->lot_image_url,
                'lot_unitPrice' => $lotInfo?->lot_unit_price,
                'lot_purchase_grant_date' => $lotInfo?->formatted_grant_date,
            ];

            foreach ($optionalFields as $k => $v) {
                if ($v !== null && $v !== '') {
                    $item[$k] = $v;
                }
            }

            return $item;
        });
    }

    /**
     * Build a normalized dictionary + compact positional tuple payload for large asset tables (6,500+ rows).
     * Reduces JSON/HTML payload by ~86% by eliminating repeated parent lot, model, and location strings per row.
     *
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>>|null $locationHierarchyMap
     * @param array<int, string>|null $projectNoMap
     * @return array<string, mixed>
     */
    public function getCompactUnitsPayload(
        array $filters = [],
        ?array $locationHierarchyMap = null,
        ?array $projectNoMap = null
    ): array {
        $locationHierarchyMap = $locationHierarchyMap ?? Location::hierarchyMap();

        // 1. Load Lot + Model + Type + Category + Brand + Uom + Organizer + Vendor dictionary once per lot
        $lotQuery = DB::table('lots as l')
            ->leftJoin('models as m', 'm.id', '=', 'l.model_id')
            ->leftJoin('types as t', 't.id', '=', 'm.type_id')
            ->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'm.brand_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'm.uom_id')
            ->leftJoin('organizers as o', 'o.id', '=', 'l.organizer_id')
            ->leftJoin('vendors as lv', 'lv.id', '=', 'l.vendor_id')
            ->leftJoin('vendors as llv', 'llv.id', '=', 'l.legacy_vendor_id')
            ->select([
                'l.id as lot_id',
                'l.number as lot_number',
                'l.organizer_id',
                'l.vendor_id as lot_vendor_id',
                'l.legacy_vendor_id as lot_legacy_vendor_id',
                'l.unit_price as lot_unit_price',
                'l.image_url as lot_image_url',
                'l.po_number as lot_po_number',
                'l.purchase_grant_date as lot_purchase_grant_date',
                'l.burden as lot_burden',
                'l.project_id as lot_project_id',
                'm.id as model_id',
                'm.number as model_number',
                'm.name as model_name',
                'm.specification as model_specification',
                't.name as type_name',
                'c.name as category_name',
                'c.code as category_code',
                'b.name as brand_name',
                'u.name as uom_name',
                'o.name as organizer_name',
                'lv.name as lot_vendor_name',
                'llv.name as lot_legacy_vendor_name',
            ]);

        if (!empty($filters['model_id'])) {
            $lotQuery->where('l.model_id', (int) $filters['model_id']);
        }
        if (!empty($filters['lot_id'])) {
            $lotQuery->where('l.id', (int) $filters['lot_id']);
        }

        $lotsDict = [];
        $referencedProjectIds = [];
        foreach ($lotQuery->get() as $lRow) {
            $lid = (int) $lRow->lot_id;
            $catCode = strtoupper((string) ($lRow->category_code ?? ''));
            $catName = strtolower((string) ($lRow->category_name ?? ''));
            $isComp = $catCode === 'COMP' || in_array($catName, ['computer', 'komputer'], true);
            $isSw = $catCode === 'SW' || $catCode === 'SOFT' || $catName === 'software' || str_contains($catName, 'perangkat lunak');
            $lotPid = $lRow->lot_project_id ? (int) $lRow->lot_project_id : null;
            if ($lotPid) {
                $referencedProjectIds[$lotPid] = true;
            }

            $lotsDict[$lid] = [
                'id' => $lid,
                'number' => $lRow->lot_number ?? '-',
                'po_number' => $lRow->lot_po_number ?? '-',
                'organizer_id' => $lRow->organizer_id ? (int) $lRow->organizer_id : null,
                'organizer' => $lRow->organizer_name ?? '-',
                'vendor_id' => $lRow->lot_vendor_id ? (int) $lRow->lot_vendor_id : null,
                'legacy_vendor_id' => $lRow->lot_legacy_vendor_id ? (int) $lRow->lot_legacy_vendor_id : null,
                'vendor' => $lRow->lot_vendor_name,
                'legacy_vendor' => $lRow->lot_legacy_vendor_name,
                'unit_price' => $lRow->lot_unit_price !== null ? (float) $lRow->lot_unit_price : null,
                'image_url' => $lRow->lot_image_url,
                'grant_date' => $lRow->lot_purchase_grant_date ? substr((string) $lRow->lot_purchase_grant_date, 0, 10) : null,
                'burden' => $lRow->lot_burden ?? 'Corporate',
                'project_id' => $lotPid,
                'barang_id' => $lRow->model_id ? (int) $lRow->model_id : null,
                'barang_code' => $lRow->model_number ?? '-',
                'barang_nama' => $lRow->model_name ?? '-',
                'barang_brand' => $lRow->brand_name ?? '-',
                'barang_specification' => $lRow->model_specification ?? '-',
                'barang_category' => $lRow->category_name ?? '-',
                'barang_category_code' => $lRow->category_code ?? null,
                'barang_subcategory' => $lRow->type_name ?? '-',
                'barang_uom' => $lRow->uom_name ?? '-',
                'is_comp' => $isComp,
                'is_sw' => $isSw,
            ];
        }

        $vendorsById = DB::table('vendors')->pluck('name', 'id')->all();

        // 2. Sparse deletion approvals dictionary
        $latestDaByAsset = [];
        $approvedDaByAsset = [];
        $daRows = DB::table('asset_deletion_approvals')
            ->select([
                'id',
                'asset_id',
                'decision',
                'proposed_condition',
                'memo_url',
                'lost_doc_url',
                'bod_boc_approval_url',
                'bod_boc_disapproval_url',
                'admin_note',
                'dm_note',
            ])
            ->orderByDesc('id')
            ->get();

        foreach ($daRows as $da) {
            $aid = (int) $da->asset_id;
            if (!isset($latestDaByAsset[$aid])) {
                $latestDaByAsset[$aid] = $da;
            }
            if ($da->decision === 'approved' && !isset($approvedDaByAsset[$aid])) {
                $approvedDaByAsset[$aid] = $da;
            }
        }

        $daDict = [];
        foreach ($latestDaByAsset as $aid => $latestDa) {
            $approvedDa = $approvedDaByAsset[$aid] ?? null;
            $hasPendingDa = ($latestDa->decision ?? null) === 'pending';
            $proposedCondition = $hasPendingDa ? ($latestDa->proposed_condition ?? null) : ($approvedDa->proposed_condition ?? null);
            $memoUrl = $hasPendingDa ? ($latestDa->memo_url ?: $approvedDa?->memo_url) : ($approvedDa?->memo_url ?: $latestDa->memo_url);
            $lostDocUrl = $hasPendingDa ? ($latestDa->lost_doc_url ?: $approvedDa?->lost_doc_url) : ($approvedDa?->lost_doc_url ?: $latestDa->lost_doc_url);
            $bodApprovalUrl = $hasPendingDa ? ($latestDa->bod_boc_approval_url ?: $approvedDa?->bod_boc_approval_url) : ($approvedDa?->bod_boc_approval_url ?: $latestDa->bod_boc_approval_url);
            $bodDisapprovalUrl = $hasPendingDa ? ($latestDa->bod_boc_disapproval_url ?: $approvedDa?->bod_boc_disapproval_url) : ($approvedDa?->bod_boc_disapproval_url ?: $latestDa->bod_boc_disapproval_url);
            $adminNote = $hasPendingDa ? ($latestDa->admin_note ?? $approvedDa?->admin_note) : ($approvedDa?->admin_note ?? $latestDa->admin_note);
            $dmNote = $hasPendingDa ? ($latestDa->dm_note ?? $approvedDa?->dm_note) : ($approvedDa?->dm_note ?? $latestDa->dm_note);

            $entry = [];
            if ($proposedCondition !== null && $proposedCondition !== '') $entry['pc'] = $proposedCondition;
            if ($memoUrl !== null && $memoUrl !== '') $entry['mu'] = $memoUrl;
            if ($lostDocUrl !== null && $lostDocUrl !== '') $entry['lu'] = $lostDocUrl;
            if ($bodApprovalUrl !== null && $bodApprovalUrl !== '') $entry['ba'] = $bodApprovalUrl;
            if ($bodDisapprovalUrl !== null && $bodDisapprovalUrl !== '') $entry['bd'] = $bodDisapprovalUrl;
            if ($adminNote !== null && $adminNote !== '') $entry['an'] = $adminNote;
            if ($dmNote !== null && $dmNote !== '') $entry['dn'] = $dmNote;

            if (!empty($entry)) {
                $daDict[$aid] = $entry;
            }
        }

        // 3. Fetch assets using PDO::FETCH_NUM to avoid hydrating 6,500+ stdClass objects
        $query = DB::table('assets as a')->select([
            'a.id',                     // 0
            'a.number',                 // 1
            'a.legacy_number',          // 2
            'a.lot_id',                 // 3
            'a.location_id',            // 4
            'a.status',                 // 5
            'a.approval_status',        // 6
            'a.condition',              // 7
            'a.borrowing_type',         // 8
            'a.classification',         // 9
            'a.comp_specification',     // 10
            'a.price',                  // 11
            'a.image_url',              // 12
            'a.vehicle_registration',   // 13
            'a.is_assigned_to_location',// 14
            'a.license_exp_date',       // 15
            'a.renewal_reminder',       // 16
            'a.burden',                 // 17
            'a.project_id',             // 18
            'a.legacy_vendor_id',       // 19
            'a.updated_at',             // 20
            'a.qc_pass',                // 21
            'a.last_qc',                // 22
        ]);

        if (!empty($filters['exclude_unverified'])) {
            $query->where('a.status', '!=', 'Belum Diverifikasi')
                ->where('a.status', 'not like', '%,Belum Diverifikasi%')
                ->where('a.status', 'not like', 'Belum Diverifikasi,%');
        }

        if (!empty($filters['only_unverified'])) {
            $query->where(function ($q) {
                $q->where('a.status', 'Belum Diverifikasi')
                    ->orWhere('a.status', 'like', '%,Belum Diverifikasi%')
                    ->orWhere('a.status', 'like', 'Belum Diverifikasi,%');
            });
        }

        if (!empty($filters['model_id'])) {
            $query->whereIn('a.lot_id', array_keys($lotsDict));
        }

        if (!empty($filters['lot_id'])) {
            $query->where('a.lot_id', (int) $filters['lot_id']);
        }

        $orderBy = $filters['order_by'] ?? 'created_at_desc';
        if ($orderBy === 'number_asc') {
            $query->orderBy('a.number');
        } else {
            $query->orderByDesc('a.created_at')->orderByDesc('a.id');
        }

        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare($query->toSql());
        $stmt->execute($query->getBindings());
        $rawRows = $stmt->fetchAll(\PDO::FETCH_NUM);

        $statusDict = [];
        $statusList = [];
        $condDict = [];
        $condList = [];
        $uatDict = [];
        $uatList = [];
        $compactRows = [];

        foreach ($rawRows as $r) {
            $st = (string) ($r[5] ?? '');
            if (!isset($statusDict[$st])) {
                $statusDict[$st] = count($statusList);
                $statusList[] = $st;
            }

            $co = (string) ($r[7] ?? '');
            if (!isset($condDict[$co])) {
                $condDict[$co] = count($condList);
                $condList[] = $co;
            }

            $uStr = (string) ($r[20] ?? '');
            $ua = strlen($uStr) >= 16
                ? substr($uStr, 8, 2) . '-' . substr($uStr, 5, 2) . '-' . substr($uStr, 0, 4) . ' ' . substr($uStr, 11, 5)
                : '-';
            if (!isset($uatDict[$ua])) {
                $uatDict[$ua] = count($uatList);
                $uatList[] = $ua;
            }

            $leg = ($r[2] !== null && $r[2] !== '' && $r[2] !== $r[1]) ? $r[2] : 0;

            $sparse = [];
            if ($r[6] !== null && $r[6] !== '') $sparse['as'] = $r[6];
            if ($r[8] !== null && $r[8] !== '' && $r[8] !== 'LT') $sparse['bt'] = $r[8];
            if ($r[9] !== null && $r[9] !== '' && $r[9] !== 'Aset') $sparse['cl'] = $r[9];
            if ($r[10] !== null && $r[10] !== '') $sparse['cs'] = $r[10];
            if ($r[11] !== null && (float) $r[11] > 0) $sparse['pr'] = (float) $r[11];
            if ($r[12] !== null && $r[12] !== '') $sparse['im'] = $r[12];
            if ($r[13] !== null && $r[13] !== '') $sparse['vr'] = $r[13];
            if ($r[14]) $sparse['al'] = 1;
            if ($r[15] !== null && $r[15] !== '') $sparse['le'] = substr((string) $r[15], 0, 10);
            if ($r[16] !== null) $sparse['rr'] = (int) $r[16];
            if ($r[17] !== null && $r[17] !== '' && $r[17] !== 'Corporate') $sparse['bu'] = $r[17];
            if ($r[18] !== null) {
                $pid = (int) $r[18];
                $sparse['pi'] = $pid;
                $referencedProjectIds[$pid] = true;
            }
            if ($r[19] !== null) {
                $sparse['lv'] = (int) $r[19];
            }
            if ($r[21] !== null) {
                $sparse['qp'] = (bool) $r[21];
            }
            if ($r[22] !== null) {
                $sparse['lq'] = substr((string) $r[22], 0, 10);
            }

            $tuple = [
                (int) $r[0],
                $r[1],
                $leg,
                $r[3] ? (int) $r[3] : 0,
                $r[4] ? (int) $r[4] : 0,
                $statusDict[$st],
                $condDict[$co],
                $uatDict[$ua],
            ];
            if (!empty($sparse)) {
                $tuple[] = $sparse;
            }
            $compactRows[] = $tuple;
        }

        // Resolve only referenced project numbers if $projectNoMap wasn't pre-supplied
        $projectsDict = [];
        if (!empty($referencedProjectIds)) {
            if ($projectNoMap !== null) {
                foreach (array_keys($referencedProjectIds) as $pid) {
                    if (isset($projectNoMap[$pid])) {
                        $projectsDict[$pid] = $projectNoMap[$pid];
                    }
                }
            } else {
                $projectsDict = TbProject::query()
                    ->toBase()
                    ->whereIn('id_project', array_keys($referencedProjectIds))
                    ->pluck('no_project', 'id_project')
                    ->all();
            }
        }

        $locsDict = [];
        foreach ($locationHierarchyMap as $lid => $locInfo) {
            $locsDict[(int) $lid] = is_array($locInfo) ? (string) ($locInfo['full_name'] ?? '-') : (string) $locInfo;
        }

        return [
            'lots' => $lotsDict,
            'locs' => $locsDict,
            'statuses' => $statusList,
            'conditions' => $condList,
            'uats' => $uatList,
            'vendors' => $vendorsById,
            'projects' => $projectsDict,
            'da' => $daDict,
            'rows' => $compactRows,
        ];
    }

    /**
     * Fetch lightweight active employee options without hydrating Eloquent User models.
     * Aggressively cached in Redis/Cache for 10 minutes to ensure instant sub-millisecond retrieval.
     */
    public function getActiveUsersForSelection(): Collection
    {
        return Cache::remember('active_users_for_selection', 600, function () {
            return DB::connection('user_hris')
                ->table('hrd_employee')
                ->select(['id', 'employee_name', 'employee_id'])
                ->where('active', 1)
                ->orderBy('employee_name')
                ->get()
                ->map(function ($u) {
                    $empId = trim((string) ($u->employee_id ?? ''));
                    return [
                        'id' => (int) $u->id,
                        'name' => "{$u->employee_name} ({$empId})",
                        'employee_id' => $empId,
                    ];
                });
        });
    }
}
