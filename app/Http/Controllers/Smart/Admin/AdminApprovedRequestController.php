<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmartRequestResource;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Approved Request Controller handling inspection and listing of approved requests awaiting admin confirmation.
 */
class AdminApprovedRequestController extends Controller
{
    /**
     * Eager-loaded relationships required for admin review.
     */
    protected array $relations = [
        'user',
        'approver',
        'approval.approver',
        'items.barang.subcategory.category',
        'items.barang.brand',
        'items.barang.uom',
        'items.subcategory.category',
        'items.subcategory.barangs.uom',
        'project',
        'department',
    ];

    /**
     * Display a list of requests with 'approve' status awaiting admin review.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with($this->relations)
            ->where('status', 'approve')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Smart/Admin/Inbox', [
            'user' => $request->user(),
            'requests' => SmartRequestResource::collection($requests)->resolve(),
        ]);
    }

    /**
     * Display specific approved request details (for detail/approval modal).
     */
    public function show(Request $request, string $id): JsonResponse|Response
    {
        $req = SmartRequest::with($this->relations)
            ->where('status', 'approve')
            ->where(function ($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                } else {
                    $query->where('uuid', $id)->orWhere('request_number', $id);
                }
            })
            ->firstOrFail();

        $resourceData = (new SmartRequestResource($req))->toArray($request);

        if ($request->wantsJson()) {
            return response()->json([
                'request' => $resourceData,
            ]);
        }

        return Inertia::render('Smart/Admin/Inbox', [
            'user' => $request->user(),
            'selectedRequest' => $resourceData,
        ]);
    }
}
