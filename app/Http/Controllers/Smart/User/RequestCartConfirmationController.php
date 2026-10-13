<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\SubmitConsumableCartRequest;
use App\Models\Cart\ConsumableBasket;
use App\Models\HrdOrgchart;
use App\Models\TbProject;
use App\Services\RequestSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Request Cart Confirmation Controller finalizing consumable supply requests and routing them to managers.
 */
class RequestCartConfirmationController extends Controller
{
    /**
     * Show the request confirmation form (RESTful alias for create).
     */
    public function index(Request $request): Response
    {
        return $this->create($request);
    }

    /**
     * Show the request confirmation form (CruddyByDesign create action).
     */
    public function create(Request $request): Response
    {
        $idsStr = $request->query('ids', '');
        $ids = array_filter(explode(',', $idsStr));

        $selectedItems = ConsumableBasket::with([
            'model.type.category',
            'model.brand',
            'model.uom',
            'type.category',
            'type.models.uom',
        ])
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'model_id' => $item->model_id,
                    'barang_id' => $item->model_id,
                    'type_id' => $item->type_id,
                    'subcategory_id' => $item->type_id,
                    'brand' => $item->model?->brand->name ?? '-',
                    'name' => $item->model?->name ?? 'Tidak Spesifik',
                    'spec' => $item->model?->specification ?? '',
                    'category' => $item->model 
                        ? ($item->model->type->category->name ?? '-') 
                        : ($item->type->category->name ?? '-'),
                    'type' => $item->model 
                        ? ($item->model->type->name ?? '-') 
                        : ($item->type->name ?? '-'),
                    'subcategory' => $item->model 
                        ? ($item->model->type->name ?? '-') 
                        : ($item->type->name ?? '-'),
                    'number' => $item->model?->number ?? '-',
                    'code' => $item->model?->number ?? '-',
                    'stock' => 0, // Stock calculation deprecated; items can be requested regardless of stock
                    'quantity' => (int) $item->quantity,
                    'uom' => $item->model?->uom?->name ?? ($item->type?->models?->first()?->uom?->name ?? 'satuan'),
                    'imageUrl' => $item->model_id
                        ? ($item->model?->image_url ? '/media/' . $item->model->image_url : null)
                        : (($firstModel = $item->type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null),
                ];
            });

        $userOrg = $request->user()?->hrdEmployee?->orgchart
            ?? ($request->user()?->hrdEmployee?->orgchart_id ? HrdOrgchart::find($request->user()->hrdEmployee->orgchart_id) : null);

        $departments = $userOrg ? [
            [
                'value' => (string) $userOrg->id,
                'label' => $userOrg->org_name,
            ]
        ] : [];

        $userEmployeeId = $request->user()?->employee_id ?? $request->user()?->username;
        $projects = $userEmployeeId
            ? TbProject::whereHas('assignProjects', function ($query) use ($userEmployeeId) {
                $query->where('npk', $userEmployeeId);
            })
                ->orderBy('project_name')
                ->get(['id_project', 'no_project', 'project_name'])
                ->map(fn($p) => [
                    'value' => (string) ($p->id_project ?? $p->id),
                    'label' => "[{$p->no_project}] {$p->project_name}"
                ])
            : collect();

        return Inertia::render('Smart/User/CartConfirmation', [
            'selectedItems' => $selectedItems,
            'departments' => $departments,
            'projects' => $projects,
        ]);
    }

    /**
     * Process confirmation of consumable supply request.
     */
    public function store(SubmitConsumableCartRequest $request, RequestSubmissionService $submissionService): RedirectResponse
    {
        $submissionService->submit($request->user(), $request->validated(), 'consumable');

        return redirect()->back()->with('success', __('requests.request_submitted_waiting_approval'));
    }
}
