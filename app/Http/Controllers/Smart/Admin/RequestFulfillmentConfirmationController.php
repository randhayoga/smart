<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Actions\Request\ProcessFulfillmentConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\ConfirmFulfillmentRequest;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for confirming request fulfillment (Full or Partial).
 */
class RequestFulfillmentConfirmationController extends Controller
{
    /**
     * Confirm unit assignments for a request following flowchart decision rules.
     */
    public function store(
        ConfirmFulfillmentRequest $request,
        string $id,
        ProcessFulfillmentConfirmation $confirmationAction
    ): JsonResponse|RedirectResponse {
        $req = SmartRequest::where(function ($query) use ($id) {
            if (is_numeric($id)) {
                $query->where('id', $id);
            } else {
                $query->where('uuid', $id)->orWhere('request_number', $id);
            }
        })
        ->where(function ($query) {
            $query->whereIn('status', ['confirm', 'partial'])
                  ->orWhere('status', 'like', '%partial%')
                  ->orWhere('status', 'like', '%menunggu_serah_terima%');
        })
        ->firstOrFail();

        $allowPartial = $request->boolean('allow_partial', false);
        $note = $request->input('note');

        $result = $confirmationAction->execute($req, $allowPartial, $note, $request->user());

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->back()->with('success', $result['message']);
    }
}
