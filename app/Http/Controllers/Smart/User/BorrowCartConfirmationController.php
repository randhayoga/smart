<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\AdmUser;
use App\Models\Cart\AssetBasket;
use App\Models\HrdOrgchart;
use App\Models\TbProject;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestStatusLog;
use Carbon\Carbon;

class BorrowCartConfirmationController extends Controller
{
    /**
     * Menampilkan halaman Konfirmasi Peminjaman (Keranjang Pinjam).
     */
    public function index(Request $request)
    {
        $idsStr = $request->query('ids', '');
        $ids = array_filter(explode(',', $idsStr));

        $selectedItems = AssetBasket::with(['barang.subcategory.category', 'barang.brand'])
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get()
            ->map(function ($item) {
                // Calculate stock of units with status 'tersedia'
                $stock = \App\Models\Inventory\Unit::whereHas('lot', function ($q) use ($item) {
                    $q->where('barang_id', $item->barang_id);
                })->where('status', 'tersedia')->count();

                return [
                    'id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'brand' => $item->barang->brand->name ?? '',
                    'spec' => $item->barang->specification ?? '',
                    'category' => $item->barang->subcategory->category->name ?? '-',
                    'subcategory' => $item->barang->subcategory->name ?? '-',
                    'stock' => $stock,
                    'quantity' => $item->quantity,
                    'imageUrl' => $item->barang->image_url ? '/storage/' . $item->barang->image_url : null,
                ];
            });

        $departments = HrdOrgchart::orderBy('org_name')->get()->map(fn($d) => [
            'value' => (string) $d->id,
            'label' => $d->org_name
        ]);

        $projects = TbProject::orderBy('project_name')->get()->map(fn($p) => [
            'value' => (string) $p->id,
            'label' => $p->project_name
        ]);

        // Default dates from query params or fallback
        $startDate = $request->query('start_date', '');
        $startTime = $request->query('start_time', '');
        $endDate = $request->query('end_date', '');
        $endTime = $request->query('end_time', '');

        return Inertia::render('Smart/User/AssetCartConfirmation', [
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
     * Proses konfirmasi peminjaman.
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
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date',
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

        // Determine approver: cari user yang employee_id-nya ada di hrd_orgcharts (mereka adalah manager)
        $currentUser = $request->user();
        $approverId = null;

        // Cari orgchart berdasarkan department_id dari request (jika corporate)
        if (!empty($validated['departemen'])) {
            $orgchart = HrdOrgchart::find((int)$validated['departemen']);
            if ($orgchart) {
                $managerUser = AdmUser::where('employee_id', $orgchart->employee_id)->first();
                $approverId = $managerUser?->id;
            }
        }

        // Fallback: ambil manager pertama yang ada di hrd_orgcharts
        if (!$approverId) {
            $managerEmployeeId = HrdOrgchart::whereNotNull('employee_id')
                ->where('org_code', '!=', 'IFS')
                ->value('employee_id');
            if ($managerEmployeeId) {
                $approverId = AdmUser::where('employee_id', $managerEmployeeId)->value('id');
            }
        }

        // Fallback terakhir: gunakan user pertama yang ada
        if (!$approverId) {
            $approverId = AdmUser::first()?->id;
        }

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
            $basketItem = AssetBasket::where('user_id', $request->user()->id)
                ->findOrFail($itemData['id']);

            RequestItem::create([
                'request_id' => $smartRequest->id,
                'barang_id' => $basketItem->barang_id,
                'quantity_requested' => $basketItem->quantity,
                'start_date' => Carbon::parse($validated['start_date']),
                'end_date' => $validated['end_date'] ? Carbon::parse($validated['end_date']) : null,
            ]);

            $basketItem->delete();
        }

        // Log status
        RequestStatusLog::create([
            'request_id' => $smartRequest->id,
            'status_from' => 'draft',
            'status_to' => 'wait',
            'changed_by' => $request->user()->id,
            'note' => 'Permintaan peminjaman diajukan',
        ]);

        return redirect()->route('smart.user.dashboard')->with('success', 'Permintaan peminjaman berhasil dikirim.');
    }
}
