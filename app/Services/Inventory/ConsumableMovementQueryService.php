<?php

namespace App\Services\Inventory;

use App\Models\HrdOrgchart;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\TbProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consumable Movement Query Service
 *
 * Computes high-performance aggregations of consumable stock-in and stock-out events
 * recorded in inventory_logs, supporting hierarchical catalog, date, utilization, and audit logs.
 */
class ConsumableMovementQueryService
{
    /**
     * Retrieve hierarchical master options for consumable filters.
     */
    public function getFilterOptions(): array
    {
        $categories = Category::whereHas('types', function ($q) {
            $q->where('is_consumable', true);
        })
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'code' => $cat->code,
                'name' => $cat->name,
            ])
            ->values()
            ->all();

        $types = Type::where('is_consumable', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category_id'])
            ->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category_id' => $type->category_id,
            ])
            ->values()
            ->all();

        $models = DB::table('models')
            ->join('types', 'models.type_id', '=', 'types.id')
            ->leftJoin('brands', 'models.brand_id', '=', 'brands.id')
            ->where('types.is_consumable', true)
            ->select([
                'models.id',
                'models.number',
                'models.name',
                'models.type_id',
                'models.specification',
                'brands.name as brand_name',
            ])
            ->orderBy('models.name')
            ->get()
            ->map(function ($model) {
                $brand = trim((string) ($model->brand_name ?? ''));
                $name = trim((string) ($model->name ?? ''));
                $spec = trim((string) ($model->specification ?? ''));

                $parts = array_filter([$brand, $name, $spec], fn ($s) => $s !== '' && $s !== '-');
                $formattedName = !empty($parts) ? implode(' ', $parts) : ($name ?: ('Model #' . $model->id));

                return [
                    'id' => (int) $model->id,
                    'number' => (string) $model->number,
                    'name' => $formattedName,
                    'type_id' => (int) $model->type_id,
                    'brand_name' => $brand,
                    'specification' => $spec,
                ];
            })
            ->values()
            ->all();

        // Departments from HRD Orgchart
        $departments = HrdOrgchart::select('id', 'org_name', 'org_code')
            ->whereNotNull('org_name')
            ->distinct()
            ->orderBy('org_name')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->org_name,
                'code' => $d->org_code,
            ])
            ->values()
            ->all();

        // Projects from TbProject
        $projects = TbProject::optionsForSelection()
            ->map(fn ($p) => [
                'id' => (int) $p['id'],
                'name' => $p['project_name'],
                'no_project' => $p['no_project'],
            ])
            ->values()
            ->all();

        // Extract distinct years from recorded movement logs
        $currentYear = Carbon::now()->year;
        $loggedYears = DB::table('inventory_logs')
            ->join('models', 'inventory_logs.model_id', '=', 'models.id')
            ->join('types', 'models.type_id', '=', 'types.id')
            ->where('types.is_consumable', true)
            ->whereIn('inventory_logs.action_type', ['stock_in', 'stock_out'])
            ->selectRaw('YEAR(inventory_logs.created_at) as log_year')
            ->distinct()
            ->pluck('log_year')
            ->map(fn ($y) => (int) $y)
            ->all();

        $availableYears = collect(array_merge([$currentYear], $loggedYears))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        // Dynamically compute available decades from earliest recorded year up to current year
        $earliestYear = collect($availableYears)->min() ?? $currentYear;
        $startDecade = (int) (floor($earliestYear / 10) * 10);
        $currentDecade = (int) (floor($currentYear / 10) * 10);

        $availableDecades = [];
        for ($d = $currentDecade; $d >= $startDecade; $d -= 10) {
            $availableDecades[] = [
                'id' => (string) $d,
                'name' => "{$d} - " . ($d + 9),
                'start_year' => $d,
                'end_year' => $d + 9,
            ];
        }

        return [
            'categories' => $categories,
            'types' => $types,
            'models' => $models,
            'departments' => $departments,
            'projects' => $projects,
            'availableDecades' => $availableDecades,
            'availableYears' => $availableYears,
        ];
    }

    /**
     * Compute aggregated stock movement metrics, trend series, and audit logs.
     */
    public function getMovementMetrics(array $filters = []): array
    {
        $baseQuery = DB::table('inventory_logs')
            ->join('models', 'inventory_logs.model_id', '=', 'models.id')
            ->join('types', 'models.type_id', '=', 'types.id')
            ->join('categories', 'types.category_id', '=', 'categories.id')
            ->leftJoin('lots', 'inventory_logs.lot_id', '=', 'lots.id')
            ->leftJoin('requests', 'inventory_logs.request_id', '=', 'requests.id')
            ->where('types.is_consumable', true)
            ->whereIn('inventory_logs.action_type', ['stock_in', 'stock_out']);

        // Scope by Category
        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $baseQuery->where('categories.id', (int) $filters['category_id']);
        }

        // Scope by Type
        if (!empty($filters['type_id']) && $filters['type_id'] !== 'all') {
            $baseQuery->where('types.id', (int) $filters['type_id']);
        }

        // Scope by Model
        if (!empty($filters['model_id']) && $filters['model_id'] !== 'all') {
            $baseQuery->where('models.id', (int) $filters['model_id']);
        }

        // Utilization and Department / Project Scope
        $utilization = !empty($filters['utilization']) && $filters['utilization'] !== 'all'
            ? strtolower((string) $filters['utilization'])
            : null;
        $deptId = !empty($filters['department_id']) && $filters['department_id'] !== 'all'
            ? (int) $filters['department_id']
            : null;
        $projId = !empty($filters['project_id']) && $filters['project_id'] !== 'all'
            ? (int) $filters['project_id']
            : null;

        if ($utilization === 'corporate') {
            $baseQuery->where(function ($q) use ($deptId) {
                $q->where('inventory_logs.action_type', 'stock_in')
                    ->orWhere(function ($sub) use ($deptId) {
                        $sub->where('inventory_logs.action_type', 'stock_out')
                            ->whereRaw("LOWER(requests.utilization) = 'corporate'");
                        if ($deptId !== null) {
                            $sub->where('requests.org_id', $deptId);
                        }
                    });
            });
        } elseif ($utilization === 'project') {
            $baseQuery->where(function ($q) use ($projId) {
                $q->where('inventory_logs.action_type', 'stock_in')
                    ->orWhere(function ($sub) use ($projId) {
                        $sub->where('inventory_logs.action_type', 'stock_out')
                            ->whereRaw("LOWER(requests.utilization) = 'project'");
                        if ($projId !== null) {
                            $sub->where('requests.project_id', $projId);
                        }
                    });
            });
        }

        // Date range filtering (Decade -> Year -> Month hierarchy)
        $now = Carbon::now();
        $currentDecade = (int) (floor($now->year / 10) * 10);
        $decadeFilter = !empty($filters['decade']) && $filters['decade'] !== 'all'
            ? (int) $filters['decade']
            : $currentDecade;

        $yearFilter = !empty($filters['year']) && $filters['year'] !== 'all'
            ? (int) $filters['year']
            : null;

        $monthFilter = ($yearFilter !== null && !empty($filters['month']) && $filters['month'] !== 'all')
            ? (int) $filters['month']
            : null;

        $startDate = null;
        $endDate = null;

        if ($yearFilter !== null) {
            if ($monthFilter !== null) {
                // Monthly view (daily breakdown 1..daysInMonth)
                $startDate = Carbon::create($yearFilter, $monthFilter, 1, 0, 0, 0);
                $endDate = $startDate->copy()->addMonth();
            } else {
                // Yearly view (monthly breakdown 1..12)
                $startDate = Carbon::create($yearFilter, 1, 1, 0, 0, 0);
                $endDate = $startDate->copy()->addYear();
            }

            $baseQuery->where('inventory_logs.created_at', '>=', $startDate)
                ->where('inventory_logs.created_at', '<', $endDate);
        } elseif ($decadeFilter !== null) {
            // Decade view (10-year period: decade to decade + 9)
            $startDate = Carbon::create($decadeFilter, 1, 1, 0, 0, 0);
            $endDate = $startDate->copy()->addYears(10);

            $baseQuery->where('inventory_logs.created_at', '>=', $startDate)
                ->where('inventory_logs.created_at', '<', $endDate);
        }

        // 1. Overall Summary KPIs
        $summaryRow = (clone $baseQuery)->select(
            DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_in' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_in"),
            DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_out' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_out"),
            DB::raw("COUNT(CASE WHEN inventory_logs.action_type = 'stock_in' THEN 1 END) as total_in_events"),
            DB::raw("COUNT(CASE WHEN inventory_logs.action_type = 'stock_out' THEN 1 END) as total_out_events"),
            DB::raw("COUNT(inventory_logs.id) as total_events")
        )->first();

        $totalIn = (int) ($summaryRow->total_in ?? 0);
        $totalOut = (int) ($summaryRow->total_out ?? 0);
        $totalInEvents = (int) ($summaryRow->total_in_events ?? 0);
        $totalOutEvents = (int) ($summaryRow->total_out_events ?? 0);
        $totalEvents = (int) ($summaryRow->total_events ?? 0);
        $netMovement = $totalIn - $totalOut;

        // 2. Trend Series
        $trends = $this->buildTrendSeries($baseQuery, $decadeFilter, $yearFilter, $monthFilter, $startDate);

        // 3. Stock Movement Ranking Table
        $ranking = $this->getMovementRanking($baseQuery);

        // 4. Stock Management Audit Logs Table
        $logs = $this->buildAuditLogs($baseQuery);

        return [
            'summary' => [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'total_in_events' => $totalInEvents,
                'total_out_events' => $totalOutEvents,
                'net_movement' => $netMovement,
                'total_events' => $totalEvents,
            ],
            'trends' => $trends,
            'ranking' => $ranking,
            'logs' => $logs,
            'filters' => [
                'category_id' => $filters['category_id'] ?? 'all',
                'type_id' => $filters['type_id'] ?? 'all',
                'model_id' => $filters['model_id'] ?? 'all',
                'utilization' => $utilization ?: 'all',
                'department_id' => $deptId ? (string) $deptId : 'all',
                'project_id' => $projId ? (string) $projId : 'all',
                'decade' => (string) $decadeFilter,
                'year' => $yearFilter ? (string) $yearFilter : '',
                'month' => $monthFilter ? (string) $monthFilter : '',
            ],
        ];
    }

    /**
     * Build continuous time-series trend buckets.
     */
    private function buildTrendSeries($query, ?int $decadeFilter, ?int $yearFilter, ?int $monthFilter, ?Carbon $startDate): array
    {
        // Case A: Specific Month of a Year (Daily breakdown 1..daysInMonth)
        if ($yearFilter !== null && $monthFilter !== null && $startDate !== null) {
            $daysInMonth = $startDate->daysInMonth;

            $rows = (clone $query)->select(
                DB::raw("DAY(inventory_logs.created_at) as period"),
                DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_in' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_out' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_out")
            )
                ->groupBy(DB::raw("DAY(inventory_logs.created_at)"))
                ->get()
                ->keyBy('period');

            $result = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $row = $rows->get($d);
                $result[] = [
                    'period' => $d,
                    'label' => sprintf('%02d', $d),
                    'total_in' => (int) ($row->total_in ?? 0),
                    'total_out' => (int) ($row->total_out ?? 0),
                ];
            }
            return $result;
        }

        // Case B: Specific Year (Monthly breakdown Jan..Dec)
        if ($yearFilter !== null) {
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            $rows = (clone $query)->select(
                DB::raw("MONTH(inventory_logs.created_at) as period"),
                DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_in' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_out' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_out")
            )
                ->groupBy(DB::raw("MONTH(inventory_logs.created_at)"))
                ->get()
                ->keyBy('period');

            $result = [];
            for ($m = 1; $m <= 12; $m++) {
                $row = $rows->get($m);
                $result[] = [
                    'period' => $m,
                    'label' => $monthNames[$m - 1],
                    'total_in' => (int) ($row->total_in ?? 0),
                    'total_out' => (int) ($row->total_out ?? 0),
                ];
            }
            return $result;
        }

        // Case C: Decade Breakdown (10 years from decade to decade + 9)
        if ($decadeFilter !== null) {
            $rows = (clone $query)->select(
                DB::raw("YEAR(inventory_logs.created_at) as period"),
                DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_in' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN inventory_logs.action_type = 'stock_out' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as total_out")
            )
                ->groupBy(DB::raw("YEAR(inventory_logs.created_at)"))
                ->get()
                ->keyBy('period');

            $result = [];
            for ($yr = $decadeFilter; $yr <= $decadeFilter + 9; $yr++) {
                $row = $rows->get($yr);
                $result[] = [
                    'period' => $yr,
                    'label' => (string) $yr,
                    'total_in' => (int) ($row->total_in ?? 0),
                    'total_out' => (int) ($row->total_out ?? 0),
                ];
            }
            return $result;
        }

        return [];
    }

    /**
     * Compute hierarchical Stock Movement Ranking data with precalculated rowspans for cell merging.
     * Columns: Category, Type, Model, Departements/Projects, Request, Stock Out.
     * Sorted descending by Category total stock out, and within each model by Department/Project stock out.
     */
    public function getMovementRanking($query): array
    {
        $stockOutLogs = (clone $query)
            ->where('inventory_logs.action_type', 'stock_out')
            ->leftJoin('brands', 'models.brand_id', '=', 'brands.id')
            ->select([
                'categories.id as category_id',
                'categories.name as category_name',
                'types.id as type_id',
                'types.name as type_name',
                'models.id as model_id',
                'models.name as model_name',
                'models.specification as model_specification',
                'brands.name as brand_name',
                'requests.utilization',
                'requests.org_id',
                'requests.project_id',
                DB::raw('COUNT(DISTINCT requests.id) as request_count'),
                DB::raw('SUM(ABS(inventory_logs.quantity_change)) as stock_out_qty'),
            ])
            ->groupBy([
                'categories.id',
                'categories.name',
                'types.id',
                'types.name',
                'models.id',
                'models.name',
                'models.specification',
                'brands.name',
                'requests.utilization',
                'requests.org_id',
                'requests.project_id',
            ])
            ->get();

        if ($stockOutLogs->isEmpty()) {
            return [];
        }

        // Cross-connection resolution for department and project names
        $orgIds = $stockOutLogs->pluck('org_id')->filter()->unique()->values()->all();
        $projIds = $stockOutLogs->pluck('project_id')->filter()->unique()->values()->all();

        $orgNames = !empty($orgIds)
            ? HrdOrgchart::whereIn('id', $orgIds)->pluck('org_name', 'id')->all()
            : [];
        $projNames = !empty($projIds)
            ? TbProject::whereIn('id_project', $projIds)->pluck('project_name', 'id_project')->all()
            : [];

        // Hierarchical Aggregation: Category -> Type -> Model -> Destination
        $grouped = [];
        foreach ($stockOutLogs as $log) {
            $destName = 'Lainnya';
            if ($log->utilization === 'corporate') {
                $destName = $orgNames[$log->org_id] ?? 'Corporate';
            } elseif ($log->utilization === 'project') {
                $destName = $projNames[$log->project_id] ?? 'Project';
            }

            $catId = $log->category_id;
            $typeId = $log->type_id;
            $modelId = $log->model_id;
            $destKey = $destName;

            $modelParts = array_filter([
                $log->brand_name,
                $log->model_name,
                $log->model_specification,
            ], fn ($val) => !empty($val) && $val !== '-');
            $modelDisplayName = !empty($modelParts) ? implode(' ', $modelParts) : ($log->model_name ?: '-');

            if (!isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'id' => $catId,
                    'name' => $log->category_name,
                    'total_stock_out' => 0,
                    'types' => [],
                ];
            }
            $grouped[$catId]['total_stock_out'] += (int) $log->stock_out_qty;

            if (!isset($grouped[$catId]['types'][$typeId])) {
                $grouped[$catId]['types'][$typeId] = [
                    'id' => $typeId,
                    'name' => $log->type_name,
                    'total_stock_out' => 0,
                    'models' => [],
                ];
            }
            $grouped[$catId]['types'][$typeId]['total_stock_out'] += (int) $log->stock_out_qty;

            if (!isset($grouped[$catId]['types'][$typeId]['models'][$modelId])) {
                $grouped[$catId]['types'][$typeId]['models'][$modelId] = [
                    'id' => $modelId,
                    'name' => $modelDisplayName,
                    'total_stock_out' => 0,
                    'destinations' => [],
                ];
            }
            $grouped[$catId]['types'][$typeId]['models'][$modelId]['total_stock_out'] += (int) $log->stock_out_qty;

            if (!isset($grouped[$catId]['types'][$typeId]['models'][$modelId]['destinations'][$destKey])) {
                $grouped[$catId]['types'][$typeId]['models'][$modelId]['destinations'][$destKey] = [
                    'destination' => $destName,
                    'request_count' => 0,
                    'stock_out_qty' => 0,
                ];
            }
            $grouped[$catId]['types'][$typeId]['models'][$modelId]['destinations'][$destKey]['request_count'] += (int) $log->request_count;
            $grouped[$catId]['types'][$typeId]['models'][$modelId]['destinations'][$destKey]['stock_out_qty'] += (int) $log->stock_out_qty;
        }

        // Sort categories descending by Category Total Stock Out
        uasort($grouped, fn ($a, $b) => $b['total_stock_out'] <=> $a['total_stock_out']);

        $flattened = [];
        foreach ($grouped as $cat) {
            $types = $cat['types'];
            uasort($types, fn ($a, $b) => $b['total_stock_out'] <=> $a['total_stock_out']);

            // Calculate total rows under this category
            $catRowCount = 0;
            foreach ($types as $t) {
                foreach ($t['models'] as $m) {
                    $catRowCount += count($m['destinations']);
                }
            }

            $catFirst = true;
            foreach ($types as $t) {
                $models = $t['models'];
                uasort($models, fn ($a, $b) => $b['total_stock_out'] <=> $a['total_stock_out']);

                // Calculate total rows under this type
                $typeRowCount = 0;
                foreach ($models as $m) {
                    $typeRowCount += count($m['destinations']);
                }

                $typeFirst = true;
                foreach ($models as $m) {
                    $dests = array_values($m['destinations']);
                    // Sort destinations descending by stock out quantity
                    usort($dests, fn ($a, $b) => $b['stock_out_qty'] <=> $a['stock_out_qty']);

                    $modelRowCount = count($dests);
                    $modelFirst = true;

                    foreach ($dests as $d) {
                        $flattened[] = [
                            'category_name' => $cat['name'],
                            'category_row_span' => $catFirst ? $catRowCount : 0,
                            'type_name' => $t['name'],
                            'type_row_span' => $typeFirst ? $typeRowCount : 0,
                            'model_name' => $m['name'],
                            'model_row_span' => $modelFirst ? $modelRowCount : 0,
                            'destination' => $d['destination'],
                            'request_count' => $d['request_count'],
                            'stock_out_qty' => $d['stock_out_qty'],
                        ];
                        $catFirst = false;
                        $typeFirst = false;
                        $modelFirst = false;
                    }
                }
            }
        }

        return $flattened;
    }

    /**
     * Build detailed stock management audit logs for individual transactions.
     */
    private function buildAuditLogs($query): array
    {
        $logRows = (clone $query)->select([
            'inventory_logs.id',
            'inventory_logs.user_id',
            'inventory_logs.action_type',
            'inventory_logs.quantity_change',
            'inventory_logs.note',
            'inventory_logs.created_at',
            'inventory_logs.request_id',
            'inventory_logs.fulfillment_id',
            'inventory_logs.model_id',
            'inventory_logs.lot_id',
            'models.number as model_number',
            'models.name as model_name',
            'lots.number as lot_number',
            'requests.request_number',
        ])
            ->orderByDesc('inventory_logs.created_at')
            ->orderByDesc('inventory_logs.id')
            ->limit(500)
            ->get();

        $userIds = $logRows->pluck('user_id')->filter()->unique()->values()->all();
        $userNames = !empty($userIds)
            ? DB::connection('user_hris')
                ->table('hrd_employee')
                ->whereIn('id', $userIds)
                ->pluck('employee_name', 'id')
                ->all()
            : [];

        return $logRows->map(function ($row) use ($userNames) {
            $barangNumber = $row->model_number ?? '-';
            $barangName = $row->model_name ?? '-';
            $lotNumber = $row->lot_number ?? '-';

            $actor = ($row->user_id && isset($userNames[(int) $row->user_id]))
                ? $userNames[(int) $row->user_id]
                : ($row->user_id ? "User #{$row->user_id}" : '-');

            $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;
            $cleanModelNumber = ($barangNumber && $barangNumber !== '-') ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $barangNumber) : null;
            $cleanLotNumber = ($lotNumber && $lotNumber !== '-') ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $lotNumber) : null;

            return [
                'id' => (int) $row->id,
                'model_id' => $row->model_id ? (int) $row->model_id : null,
                'lot_id' => $row->lot_id ? (int) $row->lot_id : null,
                'model_param' => $cleanModelNumber ?: ($row->model_id ? (string) $row->model_id : null),
                'lot_param' => $cleanLotNumber ?: ($row->lot_id ? (string) $row->lot_id : null),
                'barang_number' => $barangNumber,
                'barang_name' => $barangName,
                'lot_number' => $lotNumber,
                'action_type' => $row->action_type,
                'quantity_change' => (int) $row->quantity_change,
                'actor' => $actor,
                'note' => $row->note ?? '-',
                'request_id' => $row->request_id ? (int) $row->request_id : null,
                'fulfillment_id' => $row->fulfillment_id ? (int) $row->fulfillment_id : null,
                'request_number' => $row->request_number ?? null,
                'is_consumable' => true,
                'waktu' => $createdAt ? $createdAt->format('d-m-Y H:i:s') : '-',
                'created_at_raw' => $createdAt ? $createdAt->toISOString() : null,
            ];
        })->values()->all();
    }
}
