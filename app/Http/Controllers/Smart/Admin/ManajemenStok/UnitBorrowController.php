<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\AdmUser;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitLifecycle;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\Request\RequestUnitAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Unit Borrow Controller handling direct admin borrow registrations, borrow extensions, and borrow completions.
 */
class UnitBorrowController extends Controller
{
    /**
     * Mengambil daftar user untuk opsi peminjam.
     */
    public function users(): JsonResponse
    {
        $users = AdmUser::select('id', 'name', 'employee_id')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => "{$u->name} ({$u->employee_id})",
            ]);

        return response()->json($users);
    }

    /**
     * Memulai atau memperbarui data peminjaman aktif untuk unit aset.
     */
    public function borrow(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:adm_users,id',
            'start_date' => 'required|date',
            'note' => 'nullable|string|max:2000',
        ], [
            'user_id.required' => 'Peminjam wajib dipilih.',
            'user_id.exists' => 'Peminjam tidak ditemukan.',
            'start_date.required' => 'Tanggal mulai pinjam wajib diisi.',
            'start_date.date' => 'Format tanggal mulai pinjam tidak valid.',
        ]);

        DB::transaction(function () use ($unit, $validated, $request) {
            $user = AdmUser::with('hrdEmployee.orgchart')->findOrFail($validated['user_id']);
            $borrowerName = $user->name;
            $note = $validated['note'] ?? '-';
            $startDate = Carbon::parse($validated['start_date']);

            // Cek apakah sudah ada assignment peminjaman aktif untuk unit ini
            $activeAssignment = RequestUnitAssignment::with('requestItem.request')
                ->where('unit_id', $unit->id)
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

            if ($activeAssignment && $activeAssignment->requestItem && $activeAssignment->requestItem->request) {
                // Update data request yang sedang aktif
                $smartRequest = $activeAssignment->requestItem->request;
                $smartRequest->update([
                    'user_id' => $user->id,
                    'org_id' => $user->hrdEmployee?->orgchart_id,
                    'reasoning' => $note,
                ]);

                $activeAssignment->requestItem->update([
                    'start_date' => $startDate,
                ]);

                $activeAssignment->update([
                    'assigned_at' => $startDate,
                ]);

                if ($unit->status !== 'Dipinjam') {
                    Unit::withoutEvents(fn() => $unit->update(['status' => 'Dipinjam']));
                }
            } else {
                // Generate nomor request unik: MMYYYY-XXXX (max 11 chars)
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

                // Buat Request baru
                $smartRequest = SmartRequest::create([
                    'request_number' => $requestNumber,
                    'user_id' => $user->id,
                    'approver_id' => $request->user()->id,
                    'utilization' => 'corporate',
                    'org_id' => $user->hrdEmployee?->orgchart_id,
                    'project_id' => null,
                    'reasoning' => $note,
                    'status' => 'borrow',
                ]);

                // Buat RequestItem
                $unit->loadMissing('lot.barang.subcategory');
                $barangId = $unit->lot->barang_id;
                $subcategoryId = $unit->lot->barang->subcategory_id;

                $requestItem = RequestItem::create([
                    'request_id' => $smartRequest->id,
                    'subcategory_id' => $subcategoryId,
                    'barang_id' => $barangId,
                    'quantity_requested' => 1,
                    'start_date' => $startDate,
                    'end_date' => null,
                    'status' => 'fulfilled',
                ]);

                // Buat RequestUnitAssignment (request_fulfillments)
                RequestUnitAssignment::create([
                    'request_item_id' => $requestItem->id,
                    'unit_id' => $unit->id,
                    'quantity_fulfilled' => 1,
                    'assigned_at' => $startDate,
                    'completed_at' => null,
                ]);

                // Catat log status request
                RequestStatusLog::create([
                    'request_id' => $smartRequest->id,
                    'status_from' => 'draft',
                    'status_to' => 'borrow',
                    'changed_by' => $request->user()->id,
                    'note' => "Peminjaman dicatat secara manual oleh Admin untuk {$borrowerName}.",
                ]);

                // Update status unit menjadi 'Dipinjam' tanpa event audit trail (hanya dicatat saat selesai)
                Unit::withoutEvents(fn() => $unit->update(['status' => 'Dipinjam']));
            }
        });

        return redirect()->back()->with('success', 'Data peminjaman berhasil disimpan.');
    }

    /**
     * Menyelesaikan peminjaman aktif dan mengembalikan status unit menjadi Tersedia.
     */
    public function finish(Request $request, Unit $unit): RedirectResponse
    {
        DB::transaction(function () use ($unit, $request) {
            $activeAssignment = RequestUnitAssignment::with(['requestItem.request.user'])
                ->where('unit_id', $unit->id)
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

            $now = now();
            $borrowerId = $request->user()->id;
            $finalReasoning = '-';
            $startDateRaw = $unit->updated_at ?? $now;

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

            // Tutup lifecycle aktif sebelumnya jika ada
            UnitLifecycle::where('unit_id', $unit->id)
                ->whereNull('end_date')
                ->update(['end_date' => $now]);

            // 1. Catat Jejak Audit: Peminjaman
            UnitLifecycle::create([
                'unit_id' => $unit->id,
                'action_type' => 'Peminjaman',
                'status' => 'Dipinjam',
                'condition' => $unit->condition,
                'location_id' => $unit->location_id,
                'floor_id' => $unit->floor_id,
                'room_id' => $unit->room_id,
                'start_date' => $startDateWithTime,
                'end_date' => $now,
                'actor_id' => $borrowerId,
                'note' => $finalReasoning,
                'previous_state' => ['status' => 'Tersedia'],
                'new_state' => ['status' => 'Dipinjam'],
            ]);

            // 2. Catat Jejak Audit: Pengembalian
            UnitLifecycle::create([
                'unit_id' => $unit->id,
                'action_type' => 'Pengembalian',
                'status' => 'Tersedia',
                'condition' => $unit->condition,
                'location_id' => $unit->location_id,
                'floor_id' => $unit->floor_id,
                'room_id' => $unit->room_id,
                'start_date' => $now,
                'end_date' => null,
                'actor_id' => $borrowerId,
                'note' => $finalReasoning,
                'previous_state' => ['status' => 'Dipinjam'],
                'new_state' => ['status' => 'Tersedia'],
            ]);

            // Kembalikan status unit ke Tersedia tanpa memicu event status duplicate
            Unit::withoutEvents(fn() => $unit->update(['status' => 'Tersedia']));
        });

        return redirect()->back()->with('success', 'Peminjaman selesai. Status aset kembali Tersedia.');
    }
}
