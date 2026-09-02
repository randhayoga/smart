<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\AdmUser;
use App\Models\Cart\ConsumableBasket;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Lot;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Request Cart Confirmation Controller finalizing consumable supply requests and routing them to managers.
 */
class RequestCartConfirmationController extends Controller
{
    /**
     * Menampilkan halaman Konfirmasi Permintaan (Keranjang Habis Pakai).
     */
    public function index(Request $request)
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
                if ($item->barang_id) {
                    $stock = Lot::where('barang_id', $item->barang_id)->sum('current_quantity');
                } else {
                    $stock = Lot::whereHas('barang', function ($q) use ($item) {
                        $q->where('subcategory_id', $item->subcategory_id);
                    })->sum('current_quantity');
                }

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
                    'stock' => $stock,
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
     * Proses konfirmasi permintaan: simpan ke database dan kirim notifikasi approval.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items'        => 'required|array|min:1',
            'items.*.id'   => 'required|integer',
            'pemanfaatan'  => 'required|string|in:corporate,project',
            'departemen'   => 'required_if:pemanfaatan,corporate|nullable|string',
            'project'      => 'required_if:pemanfaatan,project|nullable|string',
            'alasan'       => 'required|string|max:2000',
        ]);

        // Generate request number
        $monthYear = now()->format('mY');
        $lastRequest = SmartRequest::where('request_number', 'like', $monthYear . '-%')
            ->orderBy('id', 'desc')
            ->first();
        $seq = 1;
        if ($lastRequest) {
            $parts = explode('-', $lastRequest->request_number);
            $seq = ((int) end($parts)) + 1;
        }
        $requestNumber = $monthYear . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // Determine approver (manager):
        // If corporate, then the selected Departemen Manager is the target (the employee_id in HRD_ORGCHART)
        // Else project, then the newest (most recent) employee_id with id_rbs = P2211 is the target
        $managerUser = null;

        if ($validated['pemanfaatan'] === 'corporate' && !empty($validated['departemen'])) {
            $orgchart = HrdOrgchart::find((int)$validated['departemen']);
            if ($orgchart && $orgchart->employee_id) {
                $managerUser = AdmUser::where('employee_id', $orgchart->employee_id)->first();
            }
        } elseif ($validated['pemanfaatan'] === 'project' && !empty($validated['project'])) {
            $project = TbProject::find((int)$validated['project']);
            if ($project) {
                $assignment = TbAssignProject::where('no_project', $project->no_project)
                    ->where('id_rbs', 'P2211')
                    ->orderByDesc('start_date')
                    ->orderByDesc('id')
                    ->first();

                if ($assignment && $assignment->npk) {
                    $managerUser = AdmUser::where('employee_id', $assignment->npk)->first();
                }
            }
        }

        // Fallback: ambil manager pertama yang ada di hrd_orgcharts jika tidak ditemukan
        if (!$managerUser) {
            $managerEmployeeId = HrdOrgchart::whereNotNull('employee_id')
                ->where('org_code', '!=', 'IFS')
                ->value('employee_id');
            if ($managerEmployeeId) {
                $managerUser = AdmUser::where('employee_id', $managerEmployeeId)->first();
            }
        }

        // Fallback terakhir: gunakan user pertama yang ada
        if (!$managerUser) {
            $managerUser = AdmUser::first();
        }

        $approverId = $managerUser?->id;

        // Create Request
        $smartRequest = SmartRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $request->user()->id,
            'approver_id' => $approverId,
            'utilization' => $validated['pemanfaatan'],
            'org_id' => $validated['pemanfaatan'] === 'corporate' ? (int)$validated['departemen'] : null,
            'project_id' => $validated['pemanfaatan'] === 'project' ? (int)$validated['project'] : null,
            'reasoning' => $validated['alasan'],
            'status' => 'wait',
        ]);

        // Add items and delete from cart
        foreach ($validated['items'] as $itemData) {
            $basketItem = ConsumableBasket::where('user_id', $request->user()->id)
                ->findOrFail($itemData['id']);

            RequestItem::create([
                'request_id' => $smartRequest->id,
                'subcategory_id' => $basketItem->subcategory_id,
                'barang_id' => $basketItem->barang_id,
                'quantity_requested' => $basketItem->quantity,
            ]);

            $basketItem->delete();
        }

        // Log status
        RequestStatusLog::create([
            'request_id' => $smartRequest->id,
            'status_from' => 'draft',
            'status_to' => 'wait',
            'changed_by' => $request->user()->id,
            'note' => 'Permintaan diajukan',
        ]);

        // Send real-time notification to relevant manager
        if ($managerUser) {
            app(NotificationService::class)->notifyManagerNewRequest($smartRequest, $managerUser, 'Permintaan');
        }

        return redirect()->back()->with('success', 'Permintaan berhasil dikirim dan sedang menunggu approval.');
    }
}
