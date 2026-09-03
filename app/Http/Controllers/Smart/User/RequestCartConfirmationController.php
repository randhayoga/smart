<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
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
            'barang.subcategory.category',
            'barang.brand',
            'barang.uom',
            'subcategory.category',
            'subcategory.barangs.uom',
        ])
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'brand' => $item->barang?->brand->name ?? '-',
                    'name' => $item->barang?->name ?? 'Tidak Spesifik',
                    'spec' => $item->barang?->specification ?? '',
                    'category' => $item->barang 
                        ? ($item->barang->subcategory->category->name ?? '-') 
                        : ($item->subcategory->category->name ?? '-'),
                    'subcategory' => $item->barang 
                        ? ($item->barang->subcategory->name ?? '-') 
                        : ($item->subcategory->name ?? '-'),
                    'stock' => 0, // Stock calculation deprecated; items can be requested regardless of stock
                    'quantity' => (int) $item->quantity,
                    'uom' => $item->barang?->uom?->name ?? ($item->subcategory?->barangs?->first()?->uom?->name ?? 'satuan'),
                    'imageUrl' => $item->barang_id
                        ? ($item->barang?->image_url ? '/media/' . $item->barang->image_url : null)
                        : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null),
                ];
            });

        $departments = HrdOrgchart::orderBy('org_name')->get()->map(fn($d) => [
            'value' => (string) $d->id,
            'label' => $d->org_name
        ]);

        $userEmployeeId = $request->user()->employee_id;
        $projects = TbProject::whereHas('assignProjects', function ($query) use ($userEmployeeId) {
            $query->where('npk', $userEmployeeId);
        })
            ->orderBy('project_name')
            ->get(['id', 'no_project', 'project_name'])
            ->map(fn($p) => [
                'value' => (string) $p->id,
                'label' => "[{$p->no_project}] {$p->project_name}"
            ]);

        return Inertia::render('Smart/User/CartConfirmation', [
            'selectedItems' => $selectedItems,
            'departments' => $departments,
            'projects' => $projects,
        ]);
    }

    /**
     * Process confirmation of consumable supply request.
     */
    public function store(Request $request, RequestSubmissionService $submissionService): RedirectResponse
    {
        $validated = $request->validate([
            'items'        => 'required|array|min:1',
            'items.*.id'   => 'required|integer',
            'pemanfaatan'  => 'required|string|in:corporate,project',
            'departemen'   => 'required_if:pemanfaatan,corporate|nullable|exists:hrd_orgcharts,id',
            'project'      => 'required_if:pemanfaatan,project|nullable|exists:tb_projects,id',
            'alasan'       => 'required|string|max:2000',
        ], [
            'items.required' => 'Barang yang dipilih wajib ada.',
            'items.min' => 'Pilih minimal satu barang.',
            'pemanfaatan.required' => 'Pemanfaatan wajib dipilih.',
            'departemen.required_if' => 'Departemen wajib dipilih untuk pemanfaatan corporate.',
            'departemen.exists' => 'Departemen yang dipilih tidak valid.',
            'project.required_if' => 'Project wajib dipilih untuk pemanfaatan project.',
            'project.exists' => 'Project yang dipilih tidak valid.',
            'alasan.required' => 'Alasan permintaan wajib diisi.',
            'alasan.max' => 'Alasan permintaan maksimal 2000 karakter.',
        ]);

        $submissionService->submit($request->user(), $validated, 'consumable');

        return redirect()->back()->with('success', 'Permintaan berhasil dikirim dan sedang menunggu approval.');
    }
}
