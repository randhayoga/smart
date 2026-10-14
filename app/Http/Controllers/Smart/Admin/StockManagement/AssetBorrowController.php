<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\Request\RequestFulfillment;
use App\Models\TbProject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Asset Borrow Controller handling direct admin borrow registrations, borrow extensions, and borrow completions.
 */
class AssetBorrowController extends Controller
{
    /**
     * Retrieve user listing for borrower options.
     */
    public function users(): JsonResponse
    {
        $users = User::select('id', 'employee_name', 'employee_id')
            ->where('active', 1)
            ->orderBy('employee_name')
            ->get()
            ->map(fn($u) => [
                'id' => (int) $u->id,
                'name' => "{$u->employee_name} ({$u->employee_id})",
            ]);

        return response()->json($users);
    }

    /**
     * Start or update active borrowing record for an asset.
     */
    public function borrow(Request $request, Asset $asset): RedirectResponse
    {
        if (!in_array($asset->status, ['Tersedia', 'Dipinjam'], true)) {
            return redirect()->back()->withErrors([
                'borrow' => __('inventory.unit_not_borrowable'),
            ]);
        }

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists(User::class, 'id')],
            'start_date' => 'required|date',
            'utilization' => ['required', 'string', Rule::in(['corporate', 'project'])],
            'org_id' => ['required_if:utilization,corporate', 'nullable', Rule::exists(HrdOrgchart::class, 'id')],
            'project_id' => ['required_if:utilization,project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
            'note' => 'nullable|string|max:2000',
        ], [
            'user_id.required' => 'Peminjam wajib dipilih.',
            'user_id.exists' => 'Peminjam tidak ditemukan.',
            'start_date.required' => 'Tanggal mulai pinjam wajib diisi.',
            'start_date.date' => 'Format tanggal mulai pinjam tidak valid.',
            'utilization.required' => 'Pemanfaatan wajib dipilih.',
            'utilization.in' => 'Pemanfaatan tidak valid.',
            'org_id.required_if' => 'Departemen wajib dipilih untuk pemanfaatan Corporate.',
            'org_id.exists' => 'Departemen yang dipilih tidak valid.',
            'project_id.required_if' => 'Project wajib dipilih untuk pemanfaatan Project.',
            'project_id.exists' => 'Project yang dipilih tidak valid.',
            'note.max' => 'Catatan peminjaman maksimal 2000 karakter.',
        ]);

        DB::transaction(function () use ($asset, $validated, $request) {
            $user = User::with('orgchart')->findOrFail($validated['user_id']);
            $borrowerName = $user->name;
            $note = $validated['note'] ?? '-';
            $startDate = Carbon::parse($validated['start_date']);
            $utilization = $validated['utilization'];
            $orgId = $utilization === 'corporate' ? ($validated['org_id'] ?? $user->orgchart_id) : null;
            $projectId = $utilization === 'project' ? $validated['project_id'] : null;

            // Check if an active borrowing assignment already exists for this asset
            $activeAssignment = RequestFulfillment::with('requestItem.request')
                ->where('asset_id', $asset->id)
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

            if ($activeAssignment && $activeAssignment->requestItem && $activeAssignment->requestItem->request) {
                // Update currently active request data
                $smartRequest = $activeAssignment->requestItem->request;
                $smartRequest->update([
                    'user_id' => $user->id,
                    'utilization' => $utilization,
                    'org_id' => $orgId,
                    'project_id' => $projectId,
                    'reasoning' => $note,
                ]);

                $activeAssignment->requestItem->update([
                    'start_date' => $startDate,
                ]);

                $activeAssignment->update([
                    'assigned_at' => $startDate,
                    'confirmed_at' => $startDate,
                ]);

                if ($asset->status !== 'Dipinjam') {
                    Asset::withoutEvents(fn() => $asset->update(['status' => 'Dipinjam']));
                }
            } else {
                // Generate unique request number: MMYYYY-XXXX (max 11 chars)
                $monthYear = now()->format('mY');
                $lastRequest = SmartRequest::where('request_number', 'like', $monthYear . '-%')
                    ->orderBy('id', 'desc')
                    ->first();
                $seq = 1;
                if ($lastRequest) {
                    $parts = explode('-', $lastRequest->request_number);
                    $seq = ((int) end($parts)) + 1;
                }
                $requestNumber = $monthYear . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

                // Create new Request
                $smartRequest = SmartRequest::create([
                    'request_number' => $requestNumber,
                    'user_id' => $user->id,
                    'approver_id' => $request->user()->id,
                    'utilization' => $utilization,
                    'org_id' => $orgId,
                    'project_id' => $projectId,
                    'reasoning' => $note,
                    'status' => 'borrow',
                ]);

                // Create RequestItem
                $asset->loadMissing('lot.model.type');
                $modelId = $asset->lot->model_id;
                $typeId = $asset->lot->model->type_id;

                $requestItem = RequestItem::create([
                    'request_id' => $smartRequest->id,
                    'type_id' => $typeId,
                    'model_id' => $modelId,
                    'quantity_requested' => 1,
                    'start_date' => $startDate,
                    'end_date' => null,
                    'status' => 'fulfilled',
                ]);

                // Create RequestFulfillment
                RequestFulfillment::create([
                    'request_item_id' => $requestItem->id,
                    'asset_id' => $asset->id,
                    'quantity_fulfilled' => 1,
                    'assigned_at' => $startDate,
                    'confirmed_at' => $startDate,
                    'completed_at' => null,
                ]);

                // Record request status log
                RequestStatusLog::create([
                    'request_id' => $smartRequest->id,
                    'status_from' => 'draft',
                    'status_to' => 'borrow',
                    'changed_by' => $request->user()->id,
                    'note' => "Peminjaman dicatat secara manual oleh Admin untuk {$borrowerName}.",
                ]);

                // Update asset status to 'Dipinjam'
                Asset::withoutEvents(fn() => $asset->update(['status' => 'Dipinjam']));
            }
        });

        return redirect()->back()->with('success', __('inventory.borrow_saved'));
    }

    /**
     * Complete active borrowing and restore asset availability status.
     */
    public function finish(Request $request, Asset $asset): RedirectResponse
    {
        if ($asset->status !== 'Dipinjam') {
            return redirect()->back()->withErrors([
                'borrow' => __('inventory.unit_not_borrowable'),
            ]);
        }

        DB::transaction(function () use ($asset, $request) {
            $activeAssignment = RequestFulfillment::with(['requestItem.request.user'])
                ->where('asset_id', $asset->id)
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

            $now = now();
            $borrowerId = $request->user()->id;
            $finalReasoning = '-';
            $startDateRaw = $asset->updated_at ?? $now;

            if ($activeAssignment) {
                $activeAssignment->update(['completed_at' => $now]);

                if ($activeAssignment->requestItem) {
                    $startDateRaw = $activeAssignment->requestItem->start_date ?? $startDateRaw;
                    if ($activeAssignment->requestItem->request) {
                        $smartRequest = $activeAssignment->requestItem->request;
                        $borrowerId = $smartRequest->user_id ?? $borrowerId;
                        $finalReasoning = $smartRequest->reasoning ?? '-';

                        $smartRequest->update(['status' => 'success']);

                        RequestStatusLog::create([
                            'request_id' => $smartRequest->id,
                            'status_from' => 'borrow',
                            'status_to' => 'success',
                            'changed_by' => $request->user()->id,
                            'note' => 'Peminjaman diselesaikan oleh Admin. Aset telah kembali di gudang.',
                        ]);
                    }
                }
            }

            $startDateWithTime = Carbon::parse($startDateRaw)->setTime($now->hour, $now->minute, $now->second);
            if ($startDateWithTime->isAfter($now)) {
                $startDateWithTime = $now->copy();
            }

            // Close previously active lifecycle record if exists
            AssetLifecycle::where('asset_id', $asset->id)
                ->whereNull('end_date')
                ->update(['end_date' => $now]);

            // 1. Record Audit Trail: Borrowing
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'action_type' => 'Peminjaman',
                'status' => 'Dipinjam',
                'condition' => $asset->condition,
                'location_id' => $asset->location_id,
                'start_date' => $startDateWithTime,
                'end_date' => $now,
                'actor_id' => $borrowerId,
                'note' => $finalReasoning,
                'previous_state' => ['status' => 'Tersedia'],
                'new_state' => ['status' => 'Dipinjam'],
            ]);

            // 2. Record Audit Trail: Return
            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'action_type' => 'Pengembalian',
                'status' => 'Tersedia',
                'condition' => $asset->condition,
                'location_id' => $asset->location_id,
                'start_date' => $now,
                'end_date' => null,
                'actor_id' => $borrowerId,
                'note' => $finalReasoning,
                'previous_state' => ['status' => 'Dipinjam'],
                'new_state' => ['status' => 'Tersedia'],
            ]);

            // Restore asset status to 'Tersedia'
            Asset::withoutEvents(fn() => $asset->update(['status' => 'Tersedia']));
        });

        return redirect()->back()->with('success', __('inventory.borrow_completed'));
    }
}
