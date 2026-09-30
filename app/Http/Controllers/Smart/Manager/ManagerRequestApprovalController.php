<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Actions\Request\ProcessRequestApproval;
use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manager Request Approval Controller processing approval and rejection decisions on requests.
 */
class ManagerRequestApprovalController extends Controller
{
    /**
     * Process manager approval (approve) or rejection (reject) decisions for requests.
     */
    public function store(Request $request, ProcessRequestApproval $processApproval): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:requests,id',
            'action' => 'required|string|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['action'];
        $note = $validated['note'] ?? null;

        $requests = SmartRequest::where('approver_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->where('status', 'wait')
            ->get();

        foreach ($requests as $req) {
            $processApproval->execute(
                $req,
                $decision,
                $note,
                $request->user(),
                'in_app'
            );
        }

        $isMultiple = count($ids) > 1;
        $message = $decision === 'approve'
            ? ($isMultiple ? __('requests.requests_approved_multiple') : __('requests.request_approved'))
            : ($isMultiple ? __('requests.requests_rejected_multiple') : __('requests.request_rejected'));

        return redirect()->back()->with('success', $message);
    }
}
