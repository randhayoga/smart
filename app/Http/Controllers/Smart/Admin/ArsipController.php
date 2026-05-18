<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class ArsipController extends Controller
{
    /**
     * Display the arsip (archive) page.
     */
    public function index(Request $request): Response
    {
        $dbRequests = SmartRequest::with(['user', 'items.barang'])
            ->whereIn('status', ['success', 'reject', 'cancel'])
            ->latest()
            ->get();

        $arsipList = $dbRequests->map(function ($req) {
            $isLoan = $req->items->contains(function ($item) {
                return $item->barang && $item->barang->type === 'asset';
            });
            $type = $isLoan ? 'Peminjaman' : 'Permintaan';

            $statusText = 'Sukses';
            if ($req->status === 'reject') {
                $statusText = 'Ditolak';
            } elseif ($req->status === 'cancel') {
                $statusText = 'Dibatalkan';
            }

            return [
                'id' => $req->id,
                'number' => $req->request_number,
                'type' => $type,
                'status' => $statusText,
                'requester' => $req->user ? $req->user->name : 'N/A',
                'startTime' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : '-',
                'endTime' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : '-',
            ];
        });

        return Inertia::render('Smart/Admin/Arsip', [
            'user' => $request->user(),
            'arsipList' => $arsipList,
        ]);
    }

    /**
     * Display the arsip detail page.
     */
    public function show(Request $request, string $id): Response
    {
        $reqObj = SmartRequest::with([
            'user.department',
            'approver',
            'department',
            'project',
            'items.barang.subcategory.category',
            'items.barang.brand',
            'items.assignments.unit',
            'statusLogs.changedBy'
        ])->findOrFail($id);

        // Map items
        $items = $reqObj->items->map(function ($item) {
            $assets = $item->assignments->map(function ($assign) {
                return $assign->unit ? $assign->unit->number : 'N/A';
            })->toArray();

            if (empty($assets) && $item->barang && $item->barang->type === 'asset') {
                $assets = ['Belum di-alokasi'];
            }

            return [
                'id' => $item->id,
                'brand' => $item->barang && $item->barang->brand ? $item->barang->brand->name : 'Generik',
                'category' => $item->barang && $item->barang->subcategory && $item->barang->subcategory->category ? $item->barang->subcategory->category->name : 'Kategori',
                'subcategory' => $item->barang && $item->barang->subcategory ? $item->barang->subcategory->name : 'Subkategori',
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
            ];
        });

        // Construct timeline
        $timeline = [];
        
        // 1. Created
        $timeline[] = [
            'status' => 'Permintaan dibuat',
            'time' => $reqObj->created_at ? $reqObj->created_at->format('d-m-Y H:i') : '-',
            'completed' => true
        ];

        // 2. Approved
        $approveLog = $reqObj->statusLogs->where('status_to', 'approve')->first();
        if ($approveLog || !in_array($reqObj->status, ['wait', 'reject'])) {
            $approverName = $reqObj->approver ? $reqObj->approver->name : ($approveLog && $approveLog->changedBy ? $approveLog->changedBy->name : 'Manager');
            $approveTime = $approveLog ? $approveLog->created_at->format('d-m-Y H:i') : ($reqObj->created_at ? $reqObj->created_at->addHour()->format('d-m-Y H:i') : '-');
            $timeline[] = [
                'status' => 'Di-approve',
                'user' => $approverName,
                'time' => $approveTime,
                'completed' => true
            ];
        }

        // 3. Admin Confirmed
        $confirmLog = $reqObj->statusLogs->where('status_to', 'confirm')->first() ?: $reqObj->statusLogs->where('status_to', 'handover')->first();
        if ($confirmLog || in_array($reqObj->status, ['success', 'borrow', 'return'])) {
            $adminName = $confirmLog && $confirmLog->changedBy ? $confirmLog->changedBy->name : 'Admin';
            $confirmTime = $confirmLog ? $confirmLog->created_at->format('d-m-Y H:i') : ($reqObj->created_at ? $reqObj->created_at->addHours(2)->format('d-m-Y H:i') : '-');
            $timeline[] = [
                'status' => 'Dikonfirmasi',
                'user' => $adminName,
                'time' => $confirmTime,
                'completed' => true
            ];
        }

        // 4. Handover Selesai
        $borrowLog = $reqObj->statusLogs->where('status_to', 'borrow')->first();
        if ($borrowLog || $reqObj->status === 'success') {
            $handoverTime = $borrowLog ? $borrowLog->created_at->format('d-m-Y H:i') : ($reqObj->start_date ? $reqObj->start_date->format('d-m-Y H:i') : '-');
            $timeline[] = [
                'status' => 'Serah Terima Selesai',
                'time' => $handoverTime,
                'completed' => true
            ];
        }

        // 5. Final status
        $statusText = 'Transaksi Sukses';
        $statusInfo = 'Selesai diarsipkan';
        $statusClass = 'text-green-600';
        $statusCompleted = true;

        if ($reqObj->status === 'reject') {
            $statusText = 'Ditolak oleh Manager';
            $rejectLog = $reqObj->statusLogs->where('status_to', 'reject')->first();
            $statusInfo = $rejectLog ? $rejectLog->note : 'Ditolak';
            $statusClass = 'text-red-600';
            $statusCompleted = false;
        } elseif ($reqObj->status === 'cancel') {
            $statusText = 'Dibatalkan oleh Admin';
            $cancelLog = $reqObj->statusLogs->where('status_to', 'cancel')->first();
            $statusInfo = $cancelLog ? $cancelLog->note : 'Dibatalkan';
            $statusClass = 'text-gray-500';
            $statusCompleted = false;
        }

        $timeline[] = [
            'status' => $statusText,
            'info' => $statusInfo,
            'completed' => $statusCompleted,
            'active' => !$statusCompleted,
            'isFinal' => true
        ];

        // Format request info
        $utilizationText = ucfirst($reqObj->utilization);
        $relationText = '';
        if ($reqObj->utilization === 'project' && $reqObj->project) {
            $relationText = " (" . $reqObj->project->code . " / " . $reqObj->project->name . ")";
        } elseif ($reqObj->department) {
            $relationText = " (" . $reqObj->department->name . ")";
        }

        $durationText = '-';
        if ($reqObj->start_date) {
            if ($reqObj->end_date) {
                $days = $reqObj->start_date->diffInDays($reqObj->end_date);
                $hours = $reqObj->start_date->diffInHours($reqObj->end_date) % 24;
                $durationText = $reqObj->start_date->format('d-m-Y H:i') . ' s.d. ' . $reqObj->end_date->format('d-m-Y H:i') . " ($days hari, $hours jam)";
            } else {
                $durationText = $reqObj->start_date->format('d-m-Y H:i') . " (Satu kali pakai)";
            }
        }

        $requestData = [
            'id' => $reqObj->id,
            'number' => $reqObj->request_number,
            'requester_name' => $reqObj->user ? $reqObj->user->name : 'N/A',
            'approver_name' => $reqObj->approver ? $reqObj->approver->name : 'N/A',
            'created_at' => $reqObj->created_at ? $reqObj->created_at->format('d/m/Y H:i') : '-',
            'utilization' => $utilizationText . $relationText,
            'duration' => $durationText,
            'reason' => $reqObj->reasoning,
        ];

        return Inertia::render('Smart/Admin/ArsipDetail', [
            'user' => $request->user(),
            'requestId' => $id,
            'requestData' => $requestData,
            'items' => $items,
            'timeline' => $timeline
        ]);
    }
}
