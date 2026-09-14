<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee Controller managing employee inventory views and active asset loan counts.
 * Adheres strictly to Cruddy by Design principles.
 */
class EmployeeController extends Controller
{
    /**
     * Display the employee inventory list page (Daftar Karyawan).
     */
    public function index(Request $request): Response
    {
        // Single grouped SQL query to calculate currently borrowed non-consumable assets per user_id
        $activeAssetCounts = DB::table('request_fulfillments as rf')
            ->join('request_items as ri', 'ri.id', '=', 'rf.request_item_id')
            ->join('requests as r', 'r.id', '=', 'ri.request_id')
            ->leftJoin('request_handovers as rh', function ($join) {
                $join->on('rh.id', '=', 'rf.handover_id')
                    ->orWhere(function ($q) {
                        $q->whereNull('rf.handover_id')->whereColumn('rh.request_id', 'r.id');
                    });
            })
            ->leftJoin('request_returns as rr', function ($join) {
                $join->on('rr.id', '=', 'rf.return_id')
                    ->orWhere(function ($q) {
                        $q->whereNull('rf.return_id')->whereColumn('rr.request_id', 'r.id');
                    });
            })
            ->whereNotNull('rf.unit_id')
            ->where(function ($q) {
                $q->whereNotNull('rf.confirmed_at')
                    ->orWhereNotNull('rh.user_confirmed_at');
            })
            ->whereNull('rf.completed_at')
            ->where(function ($q) {
                $q->whereNull('rr.id')
                    ->orWhereNull('rr.completed_at');
            })
            ->groupBy('r.user_id')
            ->select('r.user_id', DB::raw('COUNT(DISTINCT rf.unit_id) as total_assets'))
            ->pluck('total_assets', 'r.user_id');

        $employees = HrdEmployee::with(['orgchart', 'admUser'])
            ->orderBy('employee_id', 'asc')
            ->get()
            ->map(function ($emp) use ($activeAssetCounts) {
                $npk = $emp->employee_id ?? $emp->admUser?->employee_id ?? '-';
                $userId = $emp->admUser?->id;
                return [
                    'id' => $emp->id,
                    'user_id' => $userId,
                    'employee_id' => $npk,
                    'name' => $emp->employee_name ?? $emp->admUser?->name ?? '-',
                    'department' => $emp->orgchart?->org_name ?? '-',
                    'active_assets_count' => (int) ($userId ? ($activeAssetCounts[$userId] ?? 0) : 0),
                ];
            });

        $departments = HrdOrgchart::whereNotNull('org_name')
            ->orderBy('org_name', 'asc')
            ->pluck('org_name')
            ->unique()
            ->values();

        return Inertia::render('Smart/Admin/Karyawan/DaftarKaryawan', [
            'employees' => $employees,
            'departments' => $departments,
        ]);
    }
}
