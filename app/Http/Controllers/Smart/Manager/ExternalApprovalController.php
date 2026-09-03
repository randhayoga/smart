<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Actions\Request\ProcessRequestApproval;
use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ? ($smartRequest->department?->org_name ? "Corporate ({$smartRequest->department->org_name})" : 'Corporate')
            : ($smartRequest->project ? ($smartRequest->project->no_project ? "Project {$smartRequest->project->no_project} ({$smartRequest->project->project_name})" : "Project ({$smartRequest->project->project_name})") : 'Project');

        $firstItem = $smartRequest->items->first();
        $borrowPeriod = null;
        if ($firstItem && $firstItem->start_date) {
            $start = $firstItem->start_date->format('d-m-Y H:i');
            $end = $firstItem->end_date ? $firstItem->end_date->format('d-m-Y H:i') : '- (Tanpa Tenggat Waktu)';
            $borrowPeriod = "{$start} s.d. {$end}";
        }

        $items = $smartRequest->items->map(function ($item) {
            $brand = $item->barang?->brand?->name ?? '-';
            $name = $item->barang?->name ?? ($item->is_specific ? 'Barang' : 'Tidak Spesifik');
            $fullName = trim("{$brand} {$name}") ?: 'Barang';
            $spec = $item->barang?->specification ?? '';
            $subcategory = $item->subcategory?->name ?? $item->barang?->subcategory?->name ?? '-';
            $category = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $uom = $item->barang?->uom?->name ?? $item->subcategory?->barangs?->first()?->uom?->name ?? '';
            $rawImageUrl = $item->barang?->image_url ?? $item->subcategory?->barangs?->first()?->image_url ?? null;
            $imageUrl = $rawImageUrl ? '/media/' . $rawImageUrl : null;
            $isConsumable = (bool) ($item->barang?->is_consumable ?? $item->subcategory?->is_consumable ?? false);

            return [
                'id' => $item->id,
                'brand' => $brand,
                'name' => $name,
                'fullName' => $fullName,
                'subcategory' => $subcategory,
                'spec' => $spec,
                'category' => $category,
                'quantity' => $item->quantity_requested,
                'uom' => $uom,
                'imageUrl' => $imageUrl,
                'is_consumable' => $isConsumable,
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
            'borrowPeriod' => $borrowPeriod,
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
    public function show(Request $request, int|string $id): Response
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
     * Store the approval or rejection decision from the external signed review page.
     */
    public function store(Request $request, int|string $id, ProcessRequestApproval $processApproval): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        $smartRequest = SmartRequest::with(['user', 'department', 'project', 'approver'])->findOrFail($id);

        if ($smartRequest->status === 'wait') {
            $processApproval->execute(
                $smartRequest,
                $validated['action'],
                $validated['note'] ?? null,
                $smartRequest->approver_id,
                'email'
            );
        }

        $message = $validated['action'] === 'approve'
            ? 'Permohonan berhasil disetujui.'
            : 'Permohonan berhasil ditolak.';

        return redirect()->to($request->fullUrl())->with('success', $message);
    }

    /**
     * Alias for store method to support existing route naming.
     */
    public function action(Request $request, int|string $id, ProcessRequestApproval $processApproval): RedirectResponse
    {
        return $this->store($request, $id, $processApproval);
    }
}
