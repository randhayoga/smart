<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\AssetLifecycle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit Controller aggregating all asset lifecycle events into a comprehensive audit trail.
 */
class AuditController extends Controller
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

        $lifecycles = AssetLifecycle::with(['asset.lot.model', 'actor'])
            ->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'nomor_aset' => $log->asset->number ?? '-',
                    'kode_aset' => $log->asset->number ?? '-',
                    'nama_aset' => $log->asset->lot->model->name ?? '-',
                    'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                    'status' => $log->status ?? '-',
                    'action_type' => $log->action_type ?? '-',
                    'aktor' => ($log->action_type === 'Approval' && str_contains($log->note ?? '', 'BoD/BoC')) ? 'BoD/BoC' : ($log->actor->name ?? '-'),
                    'durasi' => $log->formatted_duration,
                    'catatan' => $log->note ?? '-',
                ];
            });

        return Inertia::render('Smart/Admin/AuditTrail', [
            'lifecycles' => $lifecycles,
        ]);
    }
}
