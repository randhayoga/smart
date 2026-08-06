<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\UnitLifecycle;
use Inertia\Inertia;

class AuditController extends Controller
{
    /**
     * Menampilkan halaman Jejak Audit utama (pooling audit semua unit).
     */
    public function index()
    {
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
