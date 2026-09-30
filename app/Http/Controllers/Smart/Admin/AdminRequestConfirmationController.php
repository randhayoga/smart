<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Actions\Request\ProcessAdminConfirmation;
use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin Request Confirmation Controller processing confirmation and rejection decisions by Admin.
 */
class AdminRequestConfirmationController extends Controller
{
    /**
     * Process admin confirmation (confirm) or rejection (reject) decisions for requests.
     */
    public function store(Request $request, ProcessAdminConfirmation $processConfirmation): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:requests,id',
            'action' => 'required|string|in:confirm,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];
        $note = $validated['note'] ?? null;

        $requests = SmartRequest::whereIn('id', $ids)
            ->where('status', 'approve')
            ->get();

        foreach ($requests as $req) {
            $processConfirmation->execute(
                $req,
                $action,
                $note,
                $request->user()
            );
        }

        $isMultiple = count($ids) > 1;
        $message = $action === 'confirm'
            ? ($isMultiple ? __('requests.requests_confirmed_multiple') : __('requests.request_confirmed'))
            : ($isMultiple ? __('requests.requests_rejected_multiple') : __('requests.request_rejected'));

        return redirect()->back()->with('success', $message);
    }
}
