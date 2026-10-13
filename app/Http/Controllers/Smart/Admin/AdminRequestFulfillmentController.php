<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmartFulfillmentResource;
use App\Models\Request\Request as SmartRequest;
use App\Services\RequestFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Request Fulfillment Controller handling unified show and detail inspection of confirmed/partial requests.
 */
class AdminRequestFulfillmentController extends Controller
{
    /**
     * Eager-loaded relationships required for fulfillment inspection.
     */
    protected array $relations = [
        'user.hrdEmployee.orgchart',
        'approver',
        'approval.approver',
        'adminConfirmation.admin',
        'handover',
        'department',
        'project',
        'statusLogs.changer',
        'items.model.brand',
        'items.model.uom',
        'items.model.type.category',
        'items.type.category',
        'items.type.models.uom',
        'items.fulfillments.asset.lot.model.brand',
        'items.fulfillments.asset.location.parent',
        'items.fulfillments.lot.model.brand',
        'items.fulfillments.lot.location.parent',
    ];

    /**
     * Display the fulfillment detail page for a confirmed or partial request.
     */
    public function show(Request $request, string $id, RequestFulfillmentService $fulfillmentService): JsonResponse|Response
    {
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

        // Reload fresh relations with fulfillments
        $req->load($this->relations);

        $resourceData = (new SmartFulfillmentResource($req))->toArray($request);

        if ($request->wantsJson()) {
            return response()->json([
                'request' => $resourceData,
            ]);
        }

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/Details/FulfillmentShow', [
            'user' => $request->user(),
            'request' => $resourceData,
        ]);
    }
}
