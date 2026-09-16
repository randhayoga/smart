<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\ConsumableManualRequestRequest;
use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\TbProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller handling manual requests (stopgap) and stock-outs for consumable catalog items and batch LOTs.
 */
class ConsumableManualRequestController extends Controller
{
    /**
     * Mengambil opsi data pemohon (users), departemen, dan project untuk formulir permintaan manual.
     */
    public function options(Request $request): JsonResponse
    {
        $targetUserId = $request->query('user_id');

        if ($targetUserId) {
            $user = AdmUser::with(['hrdEmployee.orgchart'])->find((int) $targetUserId);
            $userOrg = $user?->hrdEmployee?->orgchart
                ?? ($user?->hrdEmployee?->orgchart_id ? HrdOrgchart::find($user->hrdEmployee->orgchart_id) : null);

            $departments = $userOrg ? [
                [
                    'id' => (int) $userOrg->id,
                    'name' => $userOrg->org_code ? "{$userOrg->org_code} - {$userOrg->org_name}" : $userOrg->org_name,
                ]
            ] : [];

            $userEmployeeId = $user?->employee_id ?? $user?->username;
            $projects = $userEmployeeId
                ? TbProject::whereHas('assignProjects', function ($query) use ($userEmployeeId) {
                    $query->where('npk', $userEmployeeId)
                        ->where('DELETION', '0');
                })
                    ->orderBy('project_name')
                    ->get(['id_project', 'no_project', 'project_name'])
                    ->map(fn($p) => [
                        'id' => (int) ($p->id_project ?? $p->id),
                        'name' => $p->no_project ? "{$p->no_project} - {$p->project_name}" : $p->project_name,
                        'no_project' => $p->no_project,
                    ])
                    ->values()
                    ->all()
                : [];

            return response()->json([
                'departments' => $departments,
                'projects' => $projects,
            ]);
        }

        $users = AdmUser::select('id', 'name', 'username')
            ->with(['hrdEmployee.orgchart'])
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                $org = $u->hrdEmployee?->orgchart;
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee_id,
                    'department' => $org ? [
                        'id' => (int) $org->id,
                        'name' => $org->org_code ? "{$org->org_code} - {$org->org_name}" : $org->org_name,
                    ] : null,
                ];
            });

        $departments = HrdOrgchart::select('id', 'org_name', 'org_code')
            ->whereNotNull('org_name')
            ->orderBy('org_name')
            ->get()
            ->map(fn($d) => [
                'id' => (int) $d->id,
                'name' => $d->org_code ? "{$d->org_code} - {$d->org_name}" : $d->org_name,
            ]);

        $projects = TbProject::orderBy('project_name')
            ->get()
            ->map(fn($p) => [
                'id' => (int) $p->id_project,
                'name' => $p->no_project ? "{$p->no_project} - {$p->project_name}" : $p->project_name,
                'no_project' => $p->no_project,
            ]);

        return response()->json([
            'users' => $users,
            'departments' => $departments,
            'projects' => $projects,
        ]);
    }

    /**
     * Memproses permintaan manual non-spesifik untuk suatu tipe barang habis pakai (alokasi FIFO dari LOT tertua).
     */
    public function storeBarang(ConsumableManualRequestRequest $request, Barang $barang): RedirectResponse
    {
        $barang->loadMissing('subcategory.category');
        if (!$barang->is_consumable) {
            return redirect()->back()->withErrors([
                'quantity' => 'Barang ini bukan merupakan barang habis pakai.',
            ]);
        }

        $validated = $request->validated();
        $requestedQty = (int) $validated['quantity'];

        return DB::transaction(function () use ($request, $barang, $validated, $requestedQty) {
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
            $this->executeManualDeduction(
                admin: $request->user(),
                requester: $user,
                barang: $barang,
                allocations: $allocations,
                totalQty: $requestedQty,
                utilization: $validated['utilization'],
                orgId: $validated['utilization'] === 'corporate' ? ($validated['org_id'] ?? $user->hrdEmployee?->orgchart_id) : null,
                projectId: $validated['utilization'] === 'project' ? $validated['project_id'] : null,
                note: $validated['note'] ?? null
            );

            return redirect()->back()->with('success', 'Permintaan manual berhasil dicatat.');
        });
    }

    /**
     * Memproses permintaan manual spesifik untuk batch LOT tertentu.
     */
    public function storeLot(ConsumableManualRequestRequest $request, Lot $lot): RedirectResponse
    {
        $lot->loadMissing('barang.subcategory.category');
        if (!$lot->barang?->is_consumable) {
            return redirect()->back()->withErrors([
                'quantity' => 'LOT ini bukan merupakan barang habis pakai.',
            ]);
        }

        $validated = $request->validated();
        $requestedQty = (int) $validated['quantity'];

        return DB::transaction(function () use ($request, $lot, $validated, $requestedQty) {
            $lockedLot = Lot::where('id', $lot->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedLot->current_quantity < $requestedQty) {
                return redirect()->back()->withErrors([
                    'quantity' => "Stok LOT tidak mencukupi untuk jumlah yang diminta. (Tersedia: {$lockedLot->current_quantity})",
                ]);
            }

            $user = AdmUser::with('hrdEmployee.orgchart')->findOrFail($validated['user_id']);
            $allocations = [
                [
                    'lot' => $lockedLot,
                    'quantity' => $requestedQty,
                ],
            ];

            $this->executeManualDeduction(
                admin: $request->user(),
                requester: $user,
                barang: $lockedLot->barang,
                allocations: $allocations,
                totalQty: $requestedQty,
                utilization: $validated['utilization'],
                orgId: $validated['utilization'] === 'corporate' ? ($validated['org_id'] ?? $user->hrdEmployee?->orgchart_id) : null,
                projectId: $validated['utilization'] === 'project' ? $validated['project_id'] : null,
                note: $validated['note'] ?? null
            );

            return redirect()->back()->with('success', 'Permintaan manual berhasil dicatat.');
        });
    }

    /**
     * Mengeksekusi pencatatan transaksi manual stock deduction, relasi request, fulfillment, dan audit log secara atomik.
     *
     * @param array<array{lot: Lot, quantity: int}> $allocations
     */
    private function executeManualDeduction(
        AdmUser $admin,
        AdmUser $requester,
        Barang $barang,
        array $allocations,
        int $totalQty,
        string $utilization,
        ?int $orgId,
        ?int $projectId,
        ?string $note
    ): void {
        $now = now();

        // 1. Generate nomor request unik secara aman: MMYYYY-XXXX (max 11 chars)
        $monthYear = $now->format('mY');
        $lastRequest = SmartRequest::where('request_number', 'like', $monthYear . '-%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $seq = 1;
        if ($lastRequest) {
            $parts = explode('-', $lastRequest->request_number);
            $seq = ((int) end($parts)) + 1;
        }
        $requestNumber = $monthYear . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        // 2. Buat Request baru dengan status 'success'
        $smartRequest = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $requester->id,
            'approver_id' => $admin->id,
            'utilization' => $utilization,
            'org_id' => $orgId,
            'project_id' => $projectId,
            'reasoning' => $note,
            'status' => 'success',
        ]);

        // 3. Buat RequestItem terkait
        $requestItem = RequestItem::create([
            'request_id' => $smartRequest->id,
            'subcategory_id' => $barang->subcategory_id,
            'barang_id' => $barang->id,
            'quantity_requested' => $totalQty,
            'start_date' => $now,
            'end_date' => null,
            'status' => 'fulfilled',
        ]);

        // 4. Update stok LOT, buat RequestFulfillment dan InventoryLog untuk setiap alokasi LOT
        foreach ($allocations as $alloc) {
            /** @var Lot $lot */
            $lot = $alloc['lot'];
            $deductQty = (int) $alloc['quantity'];

            $prevQty = (int) $lot->current_quantity;
            $newQty = max(0, $prevQty - $deductQty);
            $lot->update(['current_quantity' => $newQty]);

            // Evict atau sesuaikan unconfirmed competing fulfillments pada permintaan lain jika stok berkurang
            $competingFulfillments = RequestFulfillment::where('lot_id', $lot->id)
                ->whereNull('unit_id')
                ->whereNull('assigned_at')
                ->whereNotIn('request_item_id', [$requestItem->id])
                ->get();

            foreach ($competingFulfillments as $cf) {
                if ($newQty <= 0) {
                    $cf->delete();
                } elseif ($cf->quantity_fulfilled > $newQty) {
                    $cf->update(['quantity_fulfilled' => $newQty]);
                }
            }

            // Buat RequestFulfillment dengan timestamps lengkap (assigned_at, confirmed_at, completed_at)
            RequestFulfillment::create([
                'request_item_id' => $requestItem->id,
                'lot_id' => $lot->id,
                'quantity_fulfilled' => $deductQty,
                'assigned_at' => $now,
                'confirmed_at' => $now,
                'completed_at' => $now,
            ]);

            // Buat InventoryLog dengan action_type 'stock_out' dan quantity_change negatif
            InventoryLog::create([
                'barang_id' => $barang->id,
                'lot_id' => $lot->id,
                'user_id' => $requester->id,
                'action_type' => 'stock_out',
                'quantity_change' => -$deductQty,
                'previous_state' => ['current_quantity' => $prevQty],
                'new_state' => ['current_quantity' => $newQty],
                'note' => $note,
                'created_at' => $now,
            ]);
        }

        // 5. Catat RequestStatusLog audit trail
        RequestStatusLog::create([
            'request_id' => $smartRequest->id,
            'status_from' => 'draft',
            'status_to' => 'success',
            'changed_by' => $admin->id,
            'note' => "Pengeluaran stok habis pakai dicatat secara manual oleh Admin untuk {$requester->name}.",
            'created_at' => $now,
        ]);
    }
}
