<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $dbRequests = SmartRequest::with(['user', 'return'])
            ->where('status', 'return')
            ->latest()
            ->get();

        $returnsList = $dbRequests->map(function ($req) {
            $daysLeft = '-';
            $returnTime = '-';
            $location = 'Ruang IFS';

            if ($req->return) {
                $returnTime = $req->return->scheduled_date ? $req->return->scheduled_date->format('d-m-Y H:i') : '-';
                $location = $req->return->location ?: 'Ruang IFS';
                if ($req->return->scheduled_date) {
                    $now = Carbon::now();
                    $diff = $now->diffInDays($req->return->scheduled_date, false);
                    $daysLeft = $diff >= 0 ? (string)$diff : 'Terlambat (' . abs($diff) . ' hari)';
                }
            }

            return [
                'id' => $req->id,
                'number' => $req->request_number,
                'borrower' => $req->user ? $req->user->name : 'N/A',
                'returnTime' => $returnTime,
                'daysLeft' => $daysLeft,
                'location' => $location,
            ];
        });

        return Inertia::render('Smart/Admin/Returns', [
            'user' => $request->user(),
            'returnsList' => $returnsList,
        ]);
    }

    public function show(Request $request, $id): Response
    {
        $reqObj = SmartRequest::with([
            'user.department',
            'approver',
            'department',
            'project',
            'return',
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
        $approverName = $reqObj->approver ? $reqObj->approver->name : ($approveLog && $approveLog->changedBy ? $approveLog->changedBy->name : 'Manager');
        $approveTime = $approveLog ? $approveLog->created_at->format('d-m-Y H:i') : ($reqObj->created_at ? $reqObj->created_at->addHour()->format('d-m-Y H:i') : '-');
        $timeline[] = [
            'status' => 'Di-approve',
            'user' => $approverName,
            'time' => $approveTime,
            'completed' => true
        ];

        // 3. Confirmed & Handover Scheduled
        $confirmLog = $reqObj->statusLogs->where('status_to', 'confirm')->first() ?: $reqObj->statusLogs->where('status_to', 'handover')->first();
        $adminName = $confirmLog && $confirmLog->changedBy ? $confirmLog->changedBy->name : 'Admin';
        $confirmTime = $confirmLog ? $confirmLog->created_at->format('d-m-Y H:i') : ($reqObj->created_at ? $reqObj->created_at->addHours(2)->format('d-m-Y H:i') : '-');
        $timeline[] = [
            'status' => 'Dikonfirmasi',
            'user' => $adminName,
            'time' => $confirmTime,
            'completed' => true
        ];

        // 4. Handover Selesai (dipinjam)
        $borrowLog = $reqObj->statusLogs->where('status_to', 'borrow')->first();
        $handoverTime = $borrowLog ? $borrowLog->created_at->format('d-m-Y H:i') : ($reqObj->start_date ? $reqObj->start_date->format('d-m-Y H:i') : '-');
        $timeline[] = [
            'status' => 'Serah Terima Selesai',
            'time' => $handoverTime,
            'completed' => true
        ];

        // 5. Return scheduled (Pending admin confirmation)
        $returnTime = '-';
        $returnMethod = '-';
        $returnLocation = '-';
        if ($reqObj->return) {
            $returnTime = $reqObj->return->scheduled_date ? $reqObj->return->scheduled_date->format('d-m-Y H:i') : '-';
            $returnMethod = $reqObj->return->method === 'pickup' ? 'Dijemput Admin' : 'Diantar sendiri';
            $returnLocation = $reqObj->return->location ?: 'Ruang IFS';
        }

        $timeline[] = [
            'status' => 'Menunggu Konfirmasi Pengembalian',
            'info' => "Jadwal: $returnTime di $returnLocation",
            'active' => true,
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

        $returnData = [
            'id' => $reqObj->id,
            'number' => $reqObj->request_number,
            'borrower' => $reqObj->user ? $reqObj->user->name : 'N/A',
            'returnTime' => $returnTime,
            'location' => $returnLocation,
            'method' => $returnMethod,
        ];

        return Inertia::render('Smart/Admin/ReturnsDetail', [
            'user' => $request->user(),
            'returnId' => $id,
            'requestData' => $requestData,
            'returnData' => $returnData,
            'items' => $items,
            'timeline' => $timeline
        ]);
    }
}
