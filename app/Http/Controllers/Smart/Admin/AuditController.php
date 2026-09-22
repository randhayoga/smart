<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\UnitLifecycle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit Controller aggregating all unit asset lifecycle events into a comprehensive audit trail.
 */
class AuditController extends Controller
{
    /**
     * Display the primary Audit Trail overview page (aggregated audit logs for all units).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user || (!$user->is_admin && !in_array($user->role, ['admin', 'ifs_manager']))) {
            abort(403, 'Unauthorized action.');
        }

        $lifecycles = UnitLifecycle::with(['unit.lot.barang', 'actor'])
            ->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'kode_aset' => $log->unit->number ?? '-',
                    'nama_aset' => $log->unit->lot->barang->name ?? '-',
                    'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                    'status' => $log->status ?? '-',
                    'action_type' => $log->action_type ?? '-',
                    'aktor' => ($log->action_type === 'Approval' && str_contains($log->note ?? '', 'BoD/BoC')) ? 'BoD/BoC' : ($log->actor->name ?? '-'),
                    'durasi' => $log->formatted_duration,
                    'catatan' => $log->note ?? '-',
                ];
            });

        return Inertia::render('Smart/Admin/JejakAudit', [
            'lifecycles' => $lifecycles,
        ]);
    }
}
