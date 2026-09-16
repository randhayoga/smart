<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Actions\Request\ProcessConsumableManualRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\ConsumableManualRequestRequest;
use App\Models\AdmUser;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Controller handling manual requests (FIFO deduction across available lots) for consumable barang items.
 * Adheres strictly to Cruddy by Design principles.
 */
class BarangManualRequestController extends Controller
{
    /**
     * Store a manual consumable request for a barang catalog item.
     */
    public function store(
        ConsumableManualRequestRequest $request,
        Barang $barang,
        ProcessConsumableManualRequest $processAction
    ): RedirectResponse {
        $barang->loadMissing('subcategory.category');
        if (!$barang->is_consumable) {
            return redirect()->back()->withErrors([
                'quantity' => 'Barang ini bukan merupakan barang habis pakai.',
            ]);
        }

        $validated = $request->validated();
        $requestedQty = (int) $validated['quantity'];

        return DB::transaction(function () use ($request, $barang, $validated, $requestedQty, $processAction) {
            // Lock semua LOT aktif untuk barang ini dengan urutan FIFO (date_of_receipt ASC, id ASC)
            $lots = Lot::where('barang_id', $barang->id)
                ->where('current_quantity', '>', 0)
                ->orderBy('date_of_receipt', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $totalAvailable = (int) $lots->sum('current_quantity');
            if ($totalAvailable < $requestedQty) {
                return redirect()->back()->withErrors([
                    'quantity' => "Stok tidak mencukupi untuk jumlah yang diminta. (Tersedia: {$totalAvailable})",
                ]);
            }

            // Hitung alokasi FIFO tiap LOT
            $remaining = $requestedQty;
            $allocations = [];
            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }
                $deductFromLot = min((int) $lot->current_quantity, $remaining);
                $allocations[] = [
                    'lot' => $lot,
                    'quantity' => $deductFromLot,
                ];
                $remaining -= $deductFromLot;
            }

            $user = AdmUser::with('hrdEmployee.orgchart')->findOrFail($validated['user_id']);
            $processAction->execute(
                admin: $request->user(),
                requester: $user,
                barang: $barang,
                allocations: $allocations,
                totalQty: $requestedQty,
                utilization: $validated['utilization'],
                orgId: $validated['utilization'] === 'corporate' ? ($validated['org_id'] ?? $user->hrdEmployee?->orgchart_id) : null,
                projectId: $validated['utilization'] === 'project' ? $validated['project_id'] : null,
                note: $validated['note'] ?? null,
                requestDate: $validated['request_date'] ?? null
            );

            return redirect()->back()->with('success', 'Permintaan manual berhasil dicatat.');
        });
    }
}
