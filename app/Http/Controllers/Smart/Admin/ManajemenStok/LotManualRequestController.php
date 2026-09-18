<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Actions\Request\ProcessConsumableManualRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\ConsumableManualRequestRequest;
use App\Models\Inventory\Lot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Controller handling manual requests for a specific batch LOT.
 * Adheres strictly to Cruddy by Design principles.
 */
class LotManualRequestController extends Controller
{
    /**
     * Store a manual consumable request for a specific lot.
     */
    public function store(
        ConsumableManualRequestRequest $request,
        Lot $lot,
        ProcessConsumableManualRequest $processAction
    ): RedirectResponse {
        $lot->loadMissing('barang.subcategory.category');
        if (!$lot->barang?->is_consumable) {
            return redirect()->back()->withErrors([
                'quantity' => 'LOT ini bukan merupakan barang habis pakai.',
            ]);
        }

        $validated = $request->validated();
        $requestedQty = (int) $validated['quantity'];

        return DB::transaction(function () use ($request, $lot, $validated, $requestedQty, $processAction) {
            $lockedLot = Lot::where('id', $lot->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedLot->current_quantity < $requestedQty) {
                return redirect()->back()->withErrors([
                    'quantity' => "Stok LOT tidak mencukupi untuk jumlah yang diminta. (Tersedia: {$lockedLot->current_quantity})",
                ]);
            }

            $user = User::with('orgchart')->findOrFail($validated['user_id']);
            $allocations = [
                [
                    'lot' => $lockedLot,
                    'quantity' => $requestedQty,
                ],
            ];

            $processAction->execute(
                admin: $request->user(),
                requester: $user,
                barang: $lockedLot->barang,
                allocations: $allocations,
                totalQty: $requestedQty,
                utilization: $validated['utilization'],
                orgId: $validated['utilization'] === 'corporate' ? ($validated['org_id'] ?? $user->orgchart_id) : null,
                projectId: $validated['utilization'] === 'project' ? $validated['project_id'] : null,
                note: $validated['note'] ?? null,
                requestDate: $validated['request_date'] ?? null
            );

            return redirect()->back()->with('success', 'Permintaan manual berhasil dicatat.');
        });
    }
}
