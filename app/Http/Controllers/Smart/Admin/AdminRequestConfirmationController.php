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
            ? ($isMultiple ? 'Beberapa permintaan berhasil dikonfirmasi.' : 'Permintaan berhasil dikonfirmasi.')
            : ($isMultiple ? 'Beberapa permintaan berhasil ditolak.' : 'Permintaan berhasil ditolak.');

        return redirect()->back()->with('success', $message);
    }
}
