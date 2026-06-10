<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use App\Models\Inventory\Unit;
use Carbon\Carbon;

class ApprovalController extends Controller
{
    /**
     * Memetakan data permintaan ke format array response.
     */
    private function mapRequest($req)
    {
        $statusMap = [
            'wait' => 'Menunggu approval',
            'approve' => 'Disetujui',
            'confirm' => 'Serah Terima',
            'handover' => 'Serah Terima',
            'borrow' => 'Dipinjam',
            'return' => 'Dipinjam',
            'success' => 'Selesai',
            'reject' => 'Ditolak',
            'cancel' => 'Dibatalkan',
        ];

        $type = $req->start_date ? 'peminjaman' : 'permintaan';
        
        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            // Count stock quantity of units in status 'tersedia'
            $stockQuantity = Unit::whereHas('lot', function ($q) use ($item) {
                $q->where('barang_id', $item->barang_id);
            })->where('status', 'tersedia')->count();

            // Get assigned assets (serial numbers)
            $assets = \App\Models\Request\RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->toArray();

            return [
                'id' => $item->id,
                'barang_id' => $item->barang_id,
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'brand' => $item->barang->brand->name ?? '-',
                'nama' => $item->barang->nama ?? '',
                'spec' => $item->barang->specification ?? '',
                'quantity' => $item->quantity_requested,
                'stockQuantity' => $stockQuantity,
                'category' => $item->barang->subcategory->category->name ?? '-',
                'imageUrl' => $item->barang->image_url ? '/storage/' . $item->barang->image_url : null,
                'assets' => $assets,
            ];
        });

        $lifecycles = $req->statusLogs->map(function ($log) use ($statusMap) {
            $actorRole = 'User';
            $actorName = $log->changer->name ?? '-';
            if ($log->changer && $log->changer->role === 'admin') {
                $actorRole = 'Admin';
            } else if ($log->changer && in_array($log->changer->role, ['manager', 'ifs_manager'])) {
                $actorRole = 'Manager';
            }

            return [
                'waktu' => $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-',
                'aksi_status' => $statusMap[$log->status_to] ?? $log->status_to,
                'aktor' => "{$actorRole}: {$actorName}",
                'durasi' => '-',
                'catatan' => $log->note ?? '-',
            ];
        })->toArray();

        return [
            'id' => $req->id,
            'number' => $req->request_number,
            'type' => $type,
            'requester' => $req->user->name ?? '-',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $statusMap[$req->status] ?? $req->status,
            'raw_status' => $req->status,
            'created_at' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'items' => $items,
            'approver' => $req->approver?->name,
            'approval_by' => $req->approval?->approver?->name,
            'approval_at' => $req->approval?->decided_at?->format('d-m-Y H:i'),
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'confirmation_at' => $req->adminConfirmation?->decided_at?->format('d-m-Y H:i'),
            'lifecycles' => $lifecycles,
        ];
    }

    /**
     * Menampilkan daftar permintaan yang perlu approval manager.
     */
    public function index(Request $request): Response
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Manager yang dapat mengakses halaman ini.');
        }

        $requests = SmartRequest::with(['user', 'approver', 'approval.approver', 'adminConfirmation.admin', 'items.barang.subcategory.category', 'items.barang.brand', 'project', 'department', 'statusLogs.changer'])
            ->where('approver_id', $request->user()->id)
            ->where('status', 'wait')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($r) => $this->mapRequest($r));

        return Inertia::render('Smart/Manager/PerluApproval', [
            'user' => $request->user(),
            'requests' => $requests,
        ]);
    }

    /**
     * Menampilkan daftar permintaan yang sudah diproses oleh manager.
     */
    public function approvedList(Request $request): Response
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Manager yang dapat mengakses halaman ini.');
        }

        $requests = SmartRequest::with(['user', 'approver', 'approval.approver', 'adminConfirmation.admin', 'items.barang.subcategory.category', 'items.barang.brand', 'project', 'department', 'statusLogs.changer'])
            ->where('approver_id', $request->user()->id)
            ->where('status', '!=', 'wait')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($r) => $this->mapRequest($r));

        return Inertia::render('Smart/Manager/SudahDiproses', [
            'user' => $request->user(),
            'requests' => $requests,
        ]);
    }

    /**
     * Menampilkan detail permintaan untuk approval.
     */
    public function show(Request $request, string $id): Response
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Manager yang dapat mengakses halaman ini.');
        }

        $req = SmartRequest::with(['user', 'approver', 'approval.approver', 'adminConfirmation.admin', 'items.barang.subcategory.category', 'items.barang.brand', 'project', 'department'])
            ->where('approver_id', $request->user()->id)
            ->findOrFail($id);

        return Inertia::render('Smart/Manager/ApprovalDetail', [
            'user' => $request->user(),
            'requestId' => $req->id,
            'request' => $this->mapRequest($req),
        ]);
    }

    /**
     * Memproses persetujuan (approve) atau penolakan (reject) oleh manager.
     */
    public function action(Request $request, $id)
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Hanya Manager yang dapat mengakses halaman ini.');
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'note' => 'nullable|string',
        ]);

        $req = SmartRequest::where('approver_id', $request->user()->id)
            ->where('status', 'wait')
            ->findOrFail($id);

        $oldStatus = $req->status;
        $decision = $validated['action'];

        RequestApproval::create([
            'request_id' => $req->id,
            'approver_id' => $request->user()->id,
            'decision' => $decision,
            'note' => $validated['note'] ?? ($decision === 'approve' ? 'Approved by Manager' : 'Rejected by Manager'),
            'decided_at' => now(),
        ]);

        $newStatus = $decision === 'approve' ? 'approve' : 'reject';
        $req->update(['status' => $newStatus]);

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => $oldStatus,
            'status_to' => $newStatus,
            'changed_by' => $request->user()->id,
            'note' => $decision === 'approve' ? 'Permintaan disetujui oleh Manager.' : 'Permintaan ditolak oleh Manager.',
        ]);

        $message = $decision === 'approve' 
            ? 'Permintaan berhasil disetujui.' 
            : 'Permintaan berhasil ditolak.';

        return redirect()->route('smart.approve')->with('success', $message);
    }

    /**
     * Memproses persetujuan (approve) atau penolakan (reject) secara massal oleh manager.
     */
    public function bulkAction(Request $request)
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:requests,id',
            'action' => 'required|string|in:approve,reject',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['action'];
        $note = $validated['note'];

        \Illuminate\Support\Facades\DB::transaction(function () use ($ids, $decision, $note, $request) {
            foreach ($ids as $id) {
                $req = SmartRequest::where('approver_id', $request->user()->id)
                    ->where('status', 'wait')
                    ->find($id);

                if (!$req) {
                    continue;
                }

                $oldStatus = $req->status;

                RequestApproval::create([
                    'request_id' => $req->id,
                    'approver_id' => $request->user()->id,
                    'decision' => $decision,
                    'note' => $note ?? ($decision === 'approve' ? 'Approved by Manager' : 'Rejected by Manager'),
                    'decided_at' => now(),
                ]);

                $newStatus = $decision === 'approve' ? 'approve' : 'reject';
                $req->update(['status' => $newStatus]);

                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => $oldStatus,
                    'status_to' => $newStatus,
                    'changed_by' => $request->user()->id,
                    'note' => $decision === 'approve' ? 'Permintaan disetujui oleh Manager.' : 'Permintaan ditolak oleh Manager.',
                ]);
            }
        });

        $message = $decision === 'approve' 
            ? 'Beberapa permintaan berhasil disetujui.' 
            : 'Beberapa permintaan berhasil ditolak.';

        return redirect()->back()->with('success', $message);
    }
}
