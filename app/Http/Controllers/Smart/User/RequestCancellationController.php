<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestStatusLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller handling user request cancellations.
 */
class RequestCancellationController extends Controller
{
    /**
     * Cancel a request that is still pending approval (status: wait).
     */
    public function store(Request $httpRequest, SmartRequest $request): RedirectResponse
    {
        if ((int) $request->user_id !== (int) $httpRequest->user()->id) {
            abort(404);
        }

        $validated = $httpRequest->validate([
            'note' => 'nullable|string|max:500',
        ]);

        if ($request->status !== 'wait') {
            return redirect()->back()->with('error', 'Hanya permintaan yang berstatus menunggu persetujuan yang dapat dibatalkan.');
        }

        $userName = $httpRequest->user()->name;

        DB::transaction(function () use ($request, $httpRequest, $validated, $userName) {
            $oldStatus = $request->status;
            $request->update(['status' => 'cancel']);
            $request->items()->update(['status' => 'cancelled']);

            RequestStatusLog::create([
                'request_id' => $request->id,
                'status_from' => $oldStatus,
                'status_to' => 'cancel',
                'changed_by' => $httpRequest->user()->id,
                'note' => !empty($validated['note']) ? $validated['note'] : "Permintaan dibatalkan oleh {$userName}.",
            ]);
        });

        return redirect()->back()->with('success', 'Permintaan berhasil dibatalkan.');
    }
}
