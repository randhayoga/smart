<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller providing administrative audit trail of all barang and LOT inventory transactions.
 * Adheres strictly to Cruddy by Design principles (standard index action for inventory logs).
 */
class InventoryAuditController extends Controller
{
    /**
     * Display a listing of inventory logs.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission('audit.view')) {
            abort(403, 'Unauthorized action.');
        }

        $rows = DB::table('inventory_logs as il')
            ->leftJoin('models as m', 'm.id', '=', 'il.model_id')
            ->leftJoin('lots as l', 'l.id', '=', 'il.lot_id')
            ->leftJoin('models as lm', 'lm.id', '=', 'l.model_id')
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

        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $userNames = !empty($userIds)
            ? DB::connection('user_hris')
                ->table('hrd_employee')
                ->whereIn('id', $userIds)
                ->pluck('employee_name', 'id')
                ->all()
            : [];

        $logs = $rows->map(function ($row) use ($userNames) {
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

        return Inertia::render('Smart/Admin/InventoryAudit', [
            'logs' => $logs,
        ]);
    }
}
