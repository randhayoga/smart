<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerRequestResource;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Approved Request Controller listing requests processed by the manager.
 */
class ManagerApprovedRequestController extends Controller
{
    /**
     * Eager-loaded relationships required for manager processed list view.
     */
    protected array $relations = [
        'user',
        'approval.approver',
        'project',
        'department',
    ];

    /**
     * Menampilkan daftar permintaan yang sudah diproses oleh manager.
     */
    public function index(Request $request): Response
    {
        $requests = SmartRequest::with($this->relations)
            ->where('approver_id', $request->user()->id)
            ->where('status', '!=', 'wait')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Smart/Manager/SudahApprove', [
            'user' => $request->user(),
            'requests' => ManagerRequestResource::collection($requests)->resolve(),
        ]);
    }
}
