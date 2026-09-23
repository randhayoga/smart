<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryLog;
use Illuminate\Http\Request;
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

        $logs = InventoryLog::with(['barang', 'lot.barang', 'user'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($log) {
                $barang = $log->barang ?? $log->lot?->barang;
                $barangNumber = $barang?->number 
                    ?? $log->previous_state['barang_number'] 
                    ?? $log->previous_state['number'] 
                    ?? '-';
                $barangName = $barang?->name 
                    ?? $log->previous_state['barang_name'] 
                    ?? $log->previous_state['name'] 
                    ?? '-';

                $lotNumber = $log->lot?->number 
                    ?? $log->previous_state['lot_number'] 
                    ?? '-';

                $actor = $log->user?->name 
                    ?? $log->previous_state['actor_name'] 
                    ?? ($log->user_id ? "User #{$log->user_id}" : '-');

                return [
                    'id' => $log->id,
                    'barang_number' => $barangNumber,
                    'barang_name' => $barangName,
                    'lot_number' => $lotNumber,
                    'action_type' => $log->action_type ?? '-',
                    'quantity_change' => (int) $log->quantity_change,
                    'actor' => $actor,
                    'note' => $log->note ?? '-',
                    'waktu' => $log->created_at ? $log->created_at->format('d-m-Y H:i:s') : '-',
                    'created_at_raw' => $log->created_at ? $log->created_at->toISOString() : null,
                ];
            });

        return Inertia::render('Smart/Admin/AuditManajemenStok', [
            'logs' => $logs,
        ]);
    }
}
