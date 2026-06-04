<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestAdminConfirmation;
use App\Models\Request\RequestStatusLog;
use App\Models\Request\RequestUnitAssignment;
use App\Models\Inventory\Unit;

class InboxController extends Controller
{
    /**
     * Menampilkan halaman Inbox Admin.
     */
    public function index(Request $request): Response
    {
        if (!in_array($request->user()->role, ['admin', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengakses halaman ini.');
        }

        $requests = SmartRequest::with(['user', 'items'])
            ->whereIn('status', ['wait', 'approve'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $amount = $req->items->sum('quantity_requested');
                $type = $req->start_date ? 'Pinjam' : 'Habis Pakai';
                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'amount' => $amount,
                    'requester' => $req->user->name ?? '-',
                    'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
                    'startTime' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : '-',
                    'endTime' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : '-',
                    'type' => $type,
                ];
            });

        return Inertia::render('Smart/Admin/Inbox', [
            'user' => $request->user(),
            'inboxItems' => $requests,
        ]);
    }

    /**
     * Menampilkan detail permintaan di halaman Inbox Admin.
     */
    public function show(Request $request, string $id): Response
    {
        if (!in_array($request->user()->role, ['admin', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengakses halaman ini.');
        }

        $req = SmartRequest::with(['user', 'approver', 'approval.approver', 'adminConfirmation.admin', 'items.barang.subcategory.category', 'items.barang.brand', 'project', 'department'])
            ->findOrFail($id);

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $hasInsufficientStock = false;
        // Map items
        $items = $req->items->map(function ($item) use (&$hasInsufficientStock) {
            // Count stock quantity of units in status 'tersedia'
            $availableStock = Unit::whereHas('lot', function ($q) use ($item) {
                $q->where('barang_id', $item->barang_id);
            })->where('status', 'tersedia')
              ->count();

            if ($availableStock < $item->quantity_requested) {
                $hasInsufficientStock = true;
            }

            // Get assigned assets (serial numbers)
            $assets = RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->toArray();

            return [
                'id' => $item->id,
                'brand' => ($item->barang->brand->name ?? '-') . ' ' . ($item->barang->specification ?? ''),
                'category' => $item->barang->subcategory->category->name ?? '-',
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $item->barang->image_url ?? null,
            ];
        });

        // Try to find approval time
        $approval = RequestApproval::where('request_id', $req->id)->where('decision', 'approve')->first();
        $approvedAt = $approval ? $approval->decided_at->format('d-m-Y H:i') : null;

        $mappedRequest = [
            'id' => $req->id,
            'number' => $req->request_number,
            'requester' => $req->user->name ?? '-',
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'type' => $req->start_date ? 'peminjaman' : 'permintaan',
            'items' => $items,
            'approvedAt' => $approvedAt,
            'has_insufficient_stock' => $hasInsufficientStock,
        ];

        return Inertia::render('Smart/Admin/InboxDetail', [
            'user' => $request->user(),
            'requestId' => $req->id,
            'request' => $mappedRequest,
        ]);
    }

    /**
     * Menyetujui (approve/confirm) atau menolak (reject) permintaan pengguna.
     */
    public function action(Request $request, $id)
    {
        if (!in_array($request->user()->role, ['admin', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengakses halaman ini.');
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject,pending',
            'note' => 'nullable|string',
        ]);

        $req = SmartRequest::findOrFail($id);
        $oldStatus = $req->status;

        if ($validated['action'] === 'pending') {
            RequestAdminConfirmation::create([
                'request_id' => $req->id,
                'admin_id' => $request->user()->id,
                'action' => 'pending',
                'note' => $validated['note'] ?? 'Pending/Delayed by Admin due to stock',
                'decided_at' => now(),
            ]);

            RequestStatusLog::create([
                'request_id' => $req->id,
                'status_from' => $oldStatus,
                'status_to' => 'pending',
                'changed_by' => $request->user()->id,
                'note' => 'Permintaan ditunda (pending) oleh Admin karena stok habis.',
            ]);

            $req->status = 'pending';
            $req->save();
        } else if ($validated['action'] === 'approve') {
            if ($req->status === 'wait') {
                RequestApproval::create([
                    'request_id' => $req->id,
                    'approver_id' => $request->user()->id,
                    'decision' => 'approve',
                    'note' => $validated['note'] ?? 'Approved by Admin (as Manager)',
                    'decided_at' => now(),
                ]);

                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => 'wait',
                    'status_to' => 'approve',
                    'changed_by' => $request->user()->id,
                    'note' => 'Permintaan disetujui oleh Manager.',
                ]);

                $req->status = 'approve';
                $req->save();
            }

            if ($req->status === 'approve') {
                // Allocate physical units
                foreach ($req->items as $item) {
                    $units = Unit::whereHas('lot', function ($q) use ($item) {
                        $q->where('barang_id', $item->barang_id);
                    })->where('status', 'tersedia')
                      ->limit($item->quantity_requested)
                      ->get();

                    if ($units->count() < $item->quantity_requested) {
                        return redirect()->back()->withErrors(['error' => "Stok unit tidak mencukupi untuk barang ID: {$item->barang_id}"]);
                    }

                    foreach ($units as $unit) {
                        RequestUnitAssignment::create([
                            'request_item_id' => $item->id,
                            'unit_id' => $unit->id,
                            'assigned_at' => now(),
                        ]);

                        // Reserve unit status
                        $isBorrow = (bool) $req->start_date;
                        $status = 'dipakai';
                        if ($isBorrow && !$unit->is_vehicle) {
                            $status = 'dipinjam';
                        }
                        $unit->update([
                            'status' => $status,
                        ]);
                    }
                }

                RequestAdminConfirmation::create([
                    'request_id' => $req->id,
                    'admin_id' => $request->user()->id,
                    'action' => 'confirm',
                    'note' => $validated['note'] ?? 'Confirmed by Admin',
                    'decided_at' => now(),
                ]);

                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => 'approve',
                    'status_to' => 'confirm',
                    'changed_by' => $request->user()->id,
                    'note' => 'Permintaan dikonfirmasi oleh Admin (Asset allocated).',
                ]);

                $req->status = 'confirm';
                $req->save();
            }
        } else {
            // Reject
            if ($req->status === 'wait') {
                RequestApproval::create([
                    'request_id' => $req->id,
                    'approver_id' => $request->user()->id,
                    'decision' => 'reject',
                    'note' => $validated['note'] ?? 'Rejected by Admin',
                    'decided_at' => now(),
                ]);
            } else if ($req->status === 'approve') {
                RequestAdminConfirmation::create([
                    'request_id' => $req->id,
                    'admin_id' => $request->user()->id,
                    'action' => 'reject',
                    'note' => $validated['note'] ?? 'Rejected by Admin',
                    'decided_at' => now(),
                ]);
            }

            $req->update(['status' => 'reject']);

            RequestStatusLog::create([
                'request_id' => $req->id,
                'status_from' => $oldStatus,
                'status_to' => 'reject',
                'changed_by' => $request->user()->id,
                'note' => 'Permintaan ditolak oleh Admin.',
            ]);
        }

        return redirect()->route('smart.inbox')->with('success', 'Permintaan berhasil diproses.');
    }
}
