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
     * Membatalkan permintaan yang masih berstatus menunggu approval (wait).
     */
    public function store(Request $request, int|string $id): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $req = SmartRequest::where('user_id', $request->user()->id)->findOrFail($id);

        if ($req->status !== 'wait') {
            return redirect()->back()->with('error', 'Hanya permintaan yang berstatus menunggu persetujuan yang dapat dibatalkan.');
        }

        $userName = $request->user()->name;

        DB::transaction(function () use ($req, $request, $validated, $userName) {
            $oldStatus = $req->status;
            $req->update(['status' => 'cancel']);

            RequestStatusLog::create([
                'request_id' => $req->id,
                'status_from' => $oldStatus,
                'status_to' => 'cancel',
                'changed_by' => $request->user()->id,
                'note' => !empty($validated['note']) ? $validated['note'] : "Permintaan dibatalkan oleh {$userName}.",
            ]);
        });

        return redirect()->back()->with('success', 'Permintaan berhasil dibatalkan.');
    }
}
