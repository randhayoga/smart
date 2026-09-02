<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Cart\AssetBasket;
use App\Models\HrdOrgchart;
use App\Models\TbProject;
use App\Services\RequestSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Borrow Cart Confirmation Controller finalizing asset borrowing requests and routing them to managers.
 */
class BorrowCartConfirmationController extends Controller
{
    /**
     * Show the borrow confirmation form (RESTful alias for create).
     */
    public function index(Request $request): Response
    {
        return $this->create($request);
    }

    /**
     * Show the borrow confirmation form (CruddyByDesign create action).
     */
    public function create(Request $request): Response
    {
        $idsStr = $request->query('ids', '');
        $ids = array_filter(explode(',', $idsStr));

        $selectedItems = AssetBasket::with([
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
                    'stock' => 0, // Stock calculation deprecated; loans can be requested regardless of stock
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

        // Default dates from query params or fallback
        $startDate = $request->query('start_date', '');
        $startTime = $request->query('start_time', '');
        $endDate = $request->query('end_date', '');
        $endTime = $request->query('end_time', '');

        return Inertia::render('Smart/User/CartConfirmation', [
            'selectedItems' => $selectedItems,
            'departments' => $departments,
            'projects' => $projects,
            'defaultStartDate' => $startDate,
            'defaultStartTime' => $startTime,
            'defaultEndDate' => $endDate,
            'defaultEndTime' => $endTime,
        ]);
    }

    /**
     * Process confirmation of asset borrowing request.
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
            'start_date'   => 'required|date|after_or_equal:today',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
        ], [
            'start_date.required' => 'Tanggal mulai peminjaman wajib diisi.',
            'start_date.date' => 'Format tanggal mulai peminjaman tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai peminjaman tidak boleh di masa lalu.',
            'end_date.date' => 'Format tanggal selesai peminjaman tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai peminjaman harus sama dengan atau setelah tanggal mulai peminjaman.',
            'items.required' => 'Barang yang dipilih wajib ada.',
            'items.min' => 'Pilih minimal satu barang.',
            'pemanfaatan.required' => 'Pemanfaatan wajib dipilih.',
            'departemen.required_if' => 'Departemen wajib dipilih untuk pemanfaatan corporate.',
            'departemen.exists' => 'Departemen yang dipilih tidak valid.',
            'project.required_if' => 'Proyek wajib dipilih untuk pemanfaatan project.',
            'project.exists' => 'Proyek yang dipilih tidak valid.',
            'alasan.required' => 'Alasan peminjaman wajib diisi.',
            'alasan.max' => 'Alasan peminjaman maksimal 2000 karakter.',
        ]);

        $submissionService->submit($request->user(), $validated, 'borrow');

        return redirect()->back()->with('success', 'Permintaan peminjaman berhasil dikirim.');
    }
}
