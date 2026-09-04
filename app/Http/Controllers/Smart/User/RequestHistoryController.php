<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmartRequestResource;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Request History Controller managing user request browsing and detail inspection.
 */
class RequestHistoryController extends Controller
{
    /**
     * Eager-loaded relationships required for user request history and details.
     */
    protected array $relations = [
        'approver',
        'items.barang.subcategory.category',
        'items.barang.brand',
        'items.barang.uom',
        'items.subcategory.category',
        'items.subcategory.barangs.uom',
        'items.fulfillments.unit',
        'project',
        'department',
        'approval.approver',
        'adminConfirmation.admin',
        'handover',
        'statusLogs.changer',
    ];

    /**
     * Display the user request and borrow history page.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with($this->relations)
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Smart/User/RequestHistory', [
            'user' => $request->user(),
            'requests' => SmartRequestResource::collection($requests)->resolve(),
        ]);
    }

    /**
     * Display the detail page for a specific request.
     */
    public function show(Request $httpRequest, SmartRequest $request): Response
    {
        if ((int) $request->user_id !== (int) $httpRequest->user()->id) {
            abort(404);
        }

        $request->loadMissing($this->relations);

        return Inertia::render('Smart/User/RequestHistoryDetail', [
            'user' => $httpRequest->user(),
            'requestId' => $request->uuid,
            'request' => (new SmartRequestResource($request))->resolve(),
            'placements' => [],
        ]);
    }
}
