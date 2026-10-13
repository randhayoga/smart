<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Actions\Request\AssignAssetsToRequestItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\AssignAssetsRequest;
use App\Models\Request\RequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for manually assigning or updating asset allocations for a request item.
 */
class RequestItemAssetAssignmentController extends Controller
{
    /**
     * Store or update assigned asset IDs for a specific request item.
     */
    public function store(
        AssignAssetsRequest $request,
        RequestItem $item,
        AssignAssetsToRequestItem $assignAction
    ): JsonResponse|RedirectResponse {
        $assetIds = $request->input('asset_ids', $request->input('unit_ids', []));
        $fulfillments = $assignAction->execute($item, $assetIds);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('requests.unit_allocation_saved'),
                'fulfillments' => $fulfillments,
            ]);
        }

        return redirect()->back()->with('success', __('requests.unit_allocation_saved'));
    }
}
