<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class InboxController extends Controller
{
    /**
     * Display the inbox page.
     */
    public function index(Request $request): Response
    {
        $dbRequests = SmartRequest::with(['user', 'items.barang.subcategory', 'items.barang.brand'])
            ->where('status', 'wait')
            ->latest()
            ->get();

        $inboxList = $dbRequests->map(function ($req) {
            $totalQty = $req->items->sum('quantity_requested');
            
            // Check type (if any item is asset, it's a loan/borrow, otherwise consumable/habis pakai)
            $isLoan = $req->items->contains(function ($item) {
                return $item->barang && $item->barang->type === 'asset';
            });
            $type = $isLoan ? 'Pinjam' : 'Habis Pakai';

            return [
                'id' => $req->id,
                'number' => $req->request_number,
                'amount' => $totalQty,
                'requester' => $req->user ? $req->user->name : 'N/A',
                'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
                'startTime' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : '-',
                'endTime' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : '-',
                'type' => $type,
            ];
        });

        return Inertia::render('Smart/Admin/Inbox', [
            'user' => $request->user(),
            'inboxList' => $inboxList,
        ]);
    }

    /**
     * Display the inbox detail page.
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

            // If it is an asset but no assignment yet, put a placeholder or empty list
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

        // Construct timeline based on status logs
        $timeline = [];
        
        // Step 1: Created (wait)
        $timeline[] = [
            'status' => 'Permintaan dibuat',
            'user' => $reqObj->user ? $reqObj->user->name : 'User',
            'time' => $reqObj->created_at ? $reqObj->created_at->format('d-m-Y H:i') : '-',
            'completed' => true
        ];

        // Step 2: Approved (if has approval or if status went past wait)
        $hasApproved = $reqObj->statusLogs->contains(function ($log) {
            return $log->status_to === 'approve' || $log->status_to === 'confirm' || $log->status_to === 'handover';
        });

        if ($hasApproved || in_array($reqObj->status, ['approve', 'confirm', 'handover', 'borrow', 'return', 'success'])) {
            $approveLog = $reqObj->statusLogs->where('status_to', 'approve')->first();
            $approverName = $reqObj->approver ? $reqObj->approver->name : ($approveLog && $approveLog->changedBy ? $approveLog->changedBy->name : 'Manager');
            $approveTime = $approveLog ? $approveLog->created_at->format('d-m-Y H:i') : ($reqObj->created_at ? $reqObj->created_at->addHour()->format('d-m-Y H:i') : '-');
            
            $timeline[] = [
                'status' => 'Di-approve',
                'user' => $approverName,
                'time' => $approveTime,
                'completed' => true
            ];
        }

        // Step 3: Admin Confirmation Needed
        if ($reqObj->status === 'wait') {
            $timeline[] = [
                'status' => 'Perlu konfirmasi Anda!',
                'active' => true,
                'isAction' => true
            ];
        }

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

        return Inertia::render('Smart/Admin/InboxDetail', [
            'user' => $request->user(),
            'requestId' => $id,
            'requestData' => $requestData,
            'items' => $items,
            'timeline' => $timeline
        ]);
    }
}
