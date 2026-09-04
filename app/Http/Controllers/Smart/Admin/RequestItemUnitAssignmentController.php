<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Actions\Request\AssignUnitsToRequestItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\AssignUnitsRequest;
use App\Models\Request\RequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for manually assigning or updating unit allocations for a request item.
 */
class RequestItemUnitAssignmentController extends Controller
{
    /**
     * Store or update assigned unit IDs for a specific request item.
     */
    public function store(
        AssignUnitsRequest $request,
        RequestItem $item,
        AssignUnitsToRequestItem $assignAction
    ): JsonResponse|RedirectResponse {
        $fulfillments = $assignAction->execute($item, $request->input('unit_ids'));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Alokasi unit aset berhasil disimpan.',
                'fulfillments' => $fulfillments,
            ]);
        }

        return redirect()->back()->with('success', 'Alokasi unit aset berhasil disimpan.');
    }
}
