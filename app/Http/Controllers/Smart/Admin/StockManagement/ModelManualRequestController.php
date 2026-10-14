<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Actions\Request\ProcessConsumableManualRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\ConsumableManualRequestRequest;
use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Controller handling manual requests (FIFO deduction across available lots) for consumable models.
 * Adheres strictly to Cruddy by Design principles.
 */
class ModelManualRequestController extends Controller
{
    /**
     * Store a manual consumable request for a catalog model.
     */
    public function store(
        ConsumableManualRequestRequest $request,
        Model $model,
        ProcessConsumableManualRequest $processAction
    ): RedirectResponse {
        $model->loadMissing('type');
        if (!$model->is_consumable) {
            return redirect()->back()->withErrors([
                'quantity' => 'Model ini bukan merupakan barang habis pakai.',
            ]);
        }

        $validated = $request->validated();
        $requestedQty = (int) $validated['quantity'];

        return DB::transaction(function () use ($request, $model, $validated, $requestedQty, $processAction) {
            // Lock semua LOT aktif untuk model ini dengan urutan FIFO (date_of_receipt ASC, id ASC)
            $lots = Lot::where('model_id', $model->id)
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

            $user = User::with('orgchart')->findOrFail($validated['user_id']);
            $processAction->execute(
                admin: $request->user(),
                requester: $user,
                model: $model,
                allocations: $allocations,
                totalQty: $requestedQty,
                utilization: $validated['utilization'],
                orgId: $validated['utilization'] === 'corporate' ? ($validated['org_id'] ?? $user->orgchart_id) : null,
                projectId: $validated['utilization'] === 'project' ? $validated['project_id'] : null,
                note: $validated['note'] ?? null,
                requestDate: $validated['request_date'] ?? null
            );

            return redirect()->back()->with('success', __('inventory.manual_request_recorded'));
        });
    }
}
