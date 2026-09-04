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
        'user.hrdEmployee',
        'approver',
        'approval.approver',
        'adminConfirmation.admin',
        'handover',
        'department',
        'project',
        'statusLogs.changer',
        'items.barang.brand',
        'items.barang.uom',
        'items.barang.subcategory.category',
        'items.subcategory.category',
        'items.subcategory.barangs.uom',
        'items.fulfillments.unit.lot.barang.brand',
        'items.fulfillments.unit.location',
        'items.fulfillments.unit.floor',
        'items.fulfillments.unit.room',
        'items.fulfillments.lot.barang.brand',
        'items.fulfillments.lot.location',
        'items.fulfillments.lot.floor',
        'items.fulfillments.lot.room',
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
        ->whereIn('status', ['confirm', 'partial'])
        ->firstOrFail();

        // Run FIFO auto-fulfillment for items needing initial assignment
        $fulfillmentService->autoFulfillRequest($req);

        // Reload fresh relations with fulfillments
        $req->load($this->relations);

        $resourceData = (new SmartFulfillmentResource($req))->toArray($request);

        if ($request->wantsJson()) {
            return response()->json([
                'request' => $resourceData,
            ]);
        }

        return Inertia::render('Smart/Admin/Fulfillment/Show', [
            'user' => $request->user(),
            'request' => $resourceData,
        ]);
    }
}
