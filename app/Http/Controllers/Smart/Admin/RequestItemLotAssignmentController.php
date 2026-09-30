<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Actions\Request\AssignLotsToRequestItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\AssignLotsRequest;
use App\Models\Request\RequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for manually assigning or updating consumable lot allocations for a request item.
 */
class RequestItemLotAssignmentController extends Controller
{
    /**
     * Store or update assigned LOT allocations for a specific request item.
     */
    public function store(
        AssignLotsRequest $request,
        RequestItem $item,
        AssignLotsToRequestItem $assignAction
    ): JsonResponse|RedirectResponse {
        $fulfillments = $assignAction->execute($item, $request->input('lot_allocations'));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('requests.lot_allocation_saved'),
                'fulfillments' => $fulfillments,
            ]);
        }

        return redirect()->back()->with('success', __('requests.lot_allocation_saved'));
    }
}
