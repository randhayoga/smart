<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller handling external zero-login manager approval workflows via HMAC-signed URLs rendered with Inertia Vue.
 */
class ExternalApprovalController extends Controller
{
    /**
     * Format a request model into props for the ExternalApproval Vue component.
     */
    private function formatRequest(SmartRequest $smartRequest): array
    {
        $type = $smartRequest->items->contains(fn($i) => $i->start_date !== null) ? 'Peminjaman' : 'Permintaan';

        $destinationName = $smartRequest->utilization === 'corporate'
            ? ($smartRequest->department?->org_name ? "Departemen {$smartRequest->department->org_name}" : 'Departemen')
            : ($smartRequest->project ? "Proyek [{$smartRequest->project->no_project}] {$smartRequest->project->project_name}" : 'Proyek');

        $firstItem = $smartRequest->items->first();
        $loanPeriod = null;
        if ($firstItem && $firstItem->start_date) {
            $start = $firstItem->start_date->format('d-m-Y H:i');
            $end = $firstItem->end_date ? $firstItem->end_date->format('d-m-Y H:i') : 'Selesai';
            $loanPeriod = "{$start} s/d {$end}";
        }

        $items = $smartRequest->items->map(function ($item) {
            $brand = $item->barang?->brand?->name;
            $name = $item->barang?->name ?? $item->subcategory?->name ?? 'Barang';
            $fullName = trim("{$brand} {$name}") ?: 'Barang';
            $spec = $item->barang?->specification ?? '';
            $category = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $uom = $item->barang?->uom?->name ?? $item->subcategory?->barangs?->first()?->uom?->name ?? '';

            return [
                'id' => $item->id,
                'name' => $fullName,
                'spec' => $spec,
                'category' => $category,
                'quantity' => $item->quantity_requested,
                'uom' => $uom,
            ];
        })->toArray();

        $approval = null;
        if ($smartRequest->approval) {
            $approval = [
                'approver_name' => $smartRequest->approval->approver?->name,
                'decision' => $smartRequest->approval->decision,
                'note' => $smartRequest->approval->note,
                'decided_at' => $smartRequest->approval->decided_at ? $smartRequest->approval->decided_at->format('d-m-Y H:i') : null,
            ];
        }

        return [
            'id' => $smartRequest->id,
            'number' => $smartRequest->request_number,
            'type' => $type,
            'requester' => $smartRequest->user?->name ?? 'Pengguna',
            'utilization' => $smartRequest->utilization,
            'destination' => $destinationName,
            'loanPeriod' => $loanPeriod,
            'reasoning' => $smartRequest->reasoning,
            'status' => $smartRequest->status,
            'rawStatus' => $smartRequest->status,
            'createdAt' => $smartRequest->created_at ? $smartRequest->created_at->format('d-m-Y H:i') : '-',
            'items' => $items,
            'approval' => $approval,
        ];
    }

    /**
     * Display the review page for a signed approval request.
     */
    public function show(Request $request, $id): Response
    {
        $smartRequest = SmartRequest::with([
            'user',
            'department',
            'project',
            'items.barang.brand',
            'items.barang.subcategory.category',
            'items.barang.uom',
            'items.subcategory.category',
            'items.subcategory.barangs.uom',
            'approval.approver',
        ])->findOrFail($id);

        return Inertia::render('Smart/Manager/ExternalApproval', [
            'request' => $this->formatRequest($smartRequest),
        ]);
    }

    /**
     * Process the approval or rejection submitted from the external signed page.
     */
    public function action(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        $smartRequest = SmartRequest::with(['user', 'department', 'project'])->findOrFail($id);

        if ($smartRequest->status === 'wait') {
            $decision = $validated['action'];
            $note = $validated['note'];
            $approverId = $smartRequest->approver_id;

            DB::transaction(function () use ($smartRequest, $decision, $note, $approverId) {
                $oldStatus = $smartRequest->status;

                RequestApproval::create([
                    'request_id' => $smartRequest->id,
                    'approver_id' => $approverId,
                    'decision' => $decision,
                    'note' => $note ?: ($decision === 'approve' ? 'Disetujui oleh Manager via Email' : 'Ditolak oleh Manager via Email'),
                    'decided_at' => now(),
                ]);

                $smartRequest->update(['status' => $decision]);

                RequestStatusLog::create([
                    'request_id' => $smartRequest->id,
                    'status_from' => $oldStatus,
                    'status_to' => $decision,
                    'changed_by' => $approverId,
                    'note' => $decision === 'approve' ? 'Permintaan disetujui oleh Manager via Email.' : 'Permintaan ditolak oleh Manager via Email.',
                ]);
            });
        }

        $message = $validated['action'] === 'approve'
            ? 'Permohonan berhasil disetujui.'
            : 'Permohonan berhasil ditolak.';

        return redirect()->to($request->fullUrl())->with('success', $message);
    }
}
