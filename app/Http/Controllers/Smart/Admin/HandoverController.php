<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class HandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $dbRequests = SmartRequest::with(['user', 'handover'])
            ->whereIn('status', ['confirm', 'handover'])
            ->latest()
            ->get();

        $handoverList = $dbRequests->map(function ($req) {
            $method = '-';
            $time = '-';
            $location = '-';

            if ($req->handover) {
                $method = $req->handover->method === 'pickup' ? 'Diambil sendiri' : 'Diantar';
                $time = $req->handover->scheduled_date ? $req->handover->scheduled_date->format('d-m-Y H:i') : '-';
                $location = $req->handover->location ?: '-';
            }

            return [
                'id' => $req->id,
                'number' => $req->request_number,
                'requester' => $req->user ? $req->user->name : 'N/A',
                'method' => $method,
                'time' => $time,
                'location' => $location,
            ];
        });

        return Inertia::render('Smart/Admin/SerahTerima', [
            'user' => $request->user(),
            'handoverList' => $handoverList,
        ]);
    }

    public function show(Request $request, $id): Response
    {
        $reqObj = SmartRequest::with([
            'user.department',
            'approver',
            'department',
            'project',
            'handover',
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

        // 3. Confirmed by Admin
        $confirmLog = $reqObj->statusLogs->where('status_to', 'confirm')->first() ?: $reqObj->statusLogs->where('status_to', 'handover')->first();
        $adminName = $confirmLog && $confirmLog->changedBy ? $confirmLog->changedBy->name : 'Admin';
        $confirmTime = $confirmLog ? $confirmLog->created_at->format('d-m-Y H:i') : ($reqObj->created_at ? $reqObj->created_at->addHours(2)->format('d-m-Y H:i') : '-');
        $timeline[] = [
            'status' => 'Dikonfirmasi',
            'user' => $adminName,
            'time' => $confirmTime,
            'completed' => true
        ];

        // 4. Handover scheduled
        $handoverTime = '-';
        $handoverMethod = '-';
        $handoverLocation = '-';
        if ($reqObj->handover) {
            $handoverTime = $reqObj->handover->scheduled_date ? $reqObj->handover->scheduled_date->format('d-m-Y H:i') : '-';
            $handoverMethod = $reqObj->handover->method === 'pickup' ? 'Diambil sendiri' : 'Diantar';
            $handoverLocation = $reqObj->handover->location ?: '-';
        }

        $timeline[] = [
            'status' => 'Serah Terima',
            'time' => $handoverTime,
            'completed' => true
        ];

        // 5. Active / final loan phase
        $isAsset = $reqObj->items->contains(function ($item) {
            return $item->barang && $item->barang->type === 'asset';
        });

        if ($isAsset) {
            $timeline[] = [
                'status' => 'Aset sedang dipinjam',
                'info' => 'Tenggat pada ' . ($reqObj->end_date ? $reqObj->end_date->format('d-m-Y H:i') : '-'),
                'active' => $reqObj->status === 'borrow',
                'isFinal' => true
            ];
        } else {
            $timeline[] = [
                'status' => 'Barang selesai diserahterimakan',
                'info' => 'Barang habis pakai (Selesai)',
                'active' => $reqObj->status === 'success',
                'isFinal' => true
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
            'created_at' => $reqObj->created_at ? $reqObj->created_at->format('d/m/Y H:i') : '-',
            'utilization' => $utilizationText . $relationText,
            'duration' => $durationText,
            'reason' => $reqObj->reasoning,
        ];

        $handoverData = [
            'id' => $reqObj->id,
            'number' => $reqObj->request_number,
            'requester' => $reqObj->user ? $reqObj->user->name : 'N/A',
            'method' => $handoverMethod,
            'time' => $handoverTime,
            'location' => $handoverLocation,
            'note' => $reqObj->handover ? $reqObj->handover->note : '',
        ];

        return Inertia::render('Smart/Admin/SerahTerimaDetail', [
            'user' => $request->user(),
            'handover' => $handoverData,
            'requestData' => $requestData,
            'items' => $items,
            'timeline' => $timeline
        ]);
    }
}
