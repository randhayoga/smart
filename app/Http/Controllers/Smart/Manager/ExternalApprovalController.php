<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Actions\Request\ProcessRequestApproval;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExternalApprovalResource;
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
     * Eager-loaded relationships required for external approval review.
     */
    protected array $relations = [
        'user',
        'department',
        'project',
        'items.barang.brand',
        'items.barang.subcategory.category',
        'items.barang.uom',
        'items.subcategory.category',
        'items.subcategory.barangs.uom',
        'approval.approver',
    ];

    /**
     * Display the review page for a signed approval request.
     */
    public function show(int|string $id): Response
    {
        $smartRequest = SmartRequest::with($this->relations)->findOrFail($id);

        return Inertia::render('Smart/Manager/ExternalApproval', [
            'request' => ExternalApprovalResource::make($smartRequest)->resolve(),
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

        $smartRequest = SmartRequest::findOrFail($id);

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
}
