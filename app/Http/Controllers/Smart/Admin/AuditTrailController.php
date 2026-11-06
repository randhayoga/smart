<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\AssetLifecycle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit Controller aggregating all asset lifecycle events into a comprehensive audit trail.
 */
class AuditTrailController extends Controller
{
    /**
     * Display the primary Audit Trail overview page (aggregated audit logs for all assets).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission('audit.view')) {
            abort(403, 'Unauthorized action.');
        }

        $rows = DB::table('asset_lifecycles as al')
            ->leftJoin('assets as a', 'a.id', '=', 'al.asset_id')
            ->leftJoin('lots as l', 'l.id', '=', 'a.lot_id')
            ->leftJoin('models as m', 'm.id', '=', 'l.model_id')
            ->leftJoin('requests as r', 'r.id', '=', 'al.request_id')
            ->select([
                'al.id',
                'al.action_type',
                'al.status',
                'al.condition',
                'al.start_date',
                'al.end_date',
                'al.actor_id',
                'al.note',
                'al.previous_state',
                'al.request_id',
                'r.request_number',
                'a.id as asset_id',
                'a.number as asset_number',
                'm.name as model_name',
            ])
            ->orderByDesc('al.start_date')
            ->orderByDesc('al.id')
            ->get();

        $actorIds = $rows->pluck('actor_id')->filter()->unique()->values()->all();
        $actorNames = !empty($actorIds)
            ? DB::connection('user_hris')
                ->table('hrd_employee')
                ->whereIn('id', $actorIds)
                ->pluck('employee_name', 'id')
                ->all()
            : [];

        $lifecycles = $rows->map(function ($row) use ($actorNames) {
            $actorName = ($row->action_type === 'Approval' && str_contains($row->note ?? '', 'BoD/BoC'))
                ? 'BoD/BoC'
                : ($row->actor_id && isset($actorNames[(int) $row->actor_id]) ? $actorNames[(int) $row->actor_id] : '-');

            $previousState = is_string($row->previous_state) ? json_decode($row->previous_state, true) : (is_array($row->previous_state) ? $row->previous_state : null);
            $proofUrl = $previousState['proof_url'] ?? null;

            return [
                'id' => (int) $row->id,
                'asset_id' => $row->asset_id ? (int) $row->asset_id : null,
                'nomor_aset' => $row->asset_number ?? '-',
                'kode_aset' => $row->asset_number ?? '-',
                'nama_aset' => $row->model_name ?? '-',
                'waktu' => $row->start_date ? Carbon::parse($row->start_date)->format('d-m-Y H:i:s') : '-',
                'status' => $row->status ?? '-',
                'condition' => $row->condition ?? '-',
                'action_type' => $row->action_type ?? '-',
                'aktor' => $actorName,
                'durasi' => AssetLifecycle::formatDurationRange($row->start_date, $row->end_date),
                'catatan' => $row->note ?? '-',
                'proof_url' => $proofUrl,
                'request_id' => $row->request_id ? (int) $row->request_id : null,
                'request_number' => $row->request_number ?? null,
            ];
        });

        return Inertia::render('Smart/Admin/AuditTrail', [
            'lifecycles' => $lifecycles,
        ]);
    }
}
