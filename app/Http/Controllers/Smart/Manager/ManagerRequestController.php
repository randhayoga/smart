<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmartRequestResource;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Request Controller handling inspection and listing of pending borrow and supply requests.
 */
class ManagerRequestController extends Controller
{
    /**
     * Eager-loaded relationships required for manager approval review.
     */
    protected array $relations = [
        'user',
        'items.barang.subcategory.category',
        'items.barang.brand',
        'items.barang.uom',
        'items.subcategory.category',
        'items.subcategory.barangs.uom',
        'project',
        'department',
    ];

    /**
     * Display a list of requests awaiting manager approval.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with($this->relations)
            ->where('approver_id', $request->user()->id)
            ->where('status', 'wait')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Smart/Manager/PerluApproval', [
            'user' => $request->user(),
            'requests' => SmartRequestResource::collection($requests)->resolve(),
        ]);
    }
}
