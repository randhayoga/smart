<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Models\Request\RequestUnitAssignment;

class ReturnController extends Controller
{
    /**
     * Menampilkan halaman daftar pengembalian aset (Returns).
     */
    public function index()
    {
        $returnsList = SmartRequest::with(['user', 'return', 'items'])
            ->where('status', 'return')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $ret = $req->return;
                $methodStr = 'Belum diatur';
                $timeStr = '-';
                $locStr = '-';

                if ($ret) {
                    $methodStr = $ret->method === 'self' ? 'Kembalikan sendiri' : 'Diantar';
                    $timeStr = $ret->scheduled_date ? $ret->scheduled_date->format('d-m-Y H:i') : '-';
                    $locStr = $ret->location ?? '-';
                }

                // Calculate days left
                $daysLeft = '-';
                if ($req->end_date) {
                    $diff = now()->diffInDays($req->end_date, false);
                    $daysLeft = $diff >= 0 ? (string)$diff : 'Telat ' . abs($diff) . ' hari';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'borrower' => $req->user->name ?? '-',
                    'method' => $methodStr,
                    'returnTime' => $timeStr,
                    'location' => $locStr,
                    'daysLeft' => $daysLeft,
                ];
            });

        return Inertia::render('Smart/Admin/Returns', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'returnsList' => $returnsList,
        ]);
    }

    /**
     * Menampilkan detail informasi pengembalian aset.
     */
    public function show($id)
    {
        $req = SmartRequest::with(['user', 'return', 'approver', 'approval.approver', 'adminConfirmation.admin', 'statusLogs.changer', 'items.barang.subcategory.category', 'items.barang.brand'])
            ->findOrFail($id);

        $ret = $req->return;
        
        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            $assets = RequestUnitAssignment::where('request_item_id', $item->id)
                ->with('unit')
                ->get()
                ->pluck('unit.number')
                ->toArray();

            return [
                'id' => $item->id,
                'brand' => ($item->barang->brand->name ?? '-') . ' ' . ($item->barang->specification ?? ''),
                'category' => $item->barang->subcategory->category->name ?? '-',
                'subcategory' => $item->barang->subcategory->name ?? '-',
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $item->barang->image_url ?? null,
            ];
        });

        $returnTimeStr = $ret && $ret->scheduled_date ? $ret->scheduled_date->format('d-m-Y H:i') : '-';
        $returnLocationStr = $ret ? $ret->location : '-';
        $returnMethodStr = $ret && $ret->method === 'self' ? 'Kembalikan sendiri' : ($ret ? 'Diantar ke GA / IT Support' : 'Belum diatur');

        $returnConfirmation = $req->statusLogs
            ->where('status_from', 'return')
            ->where('status_to', 'success')
            ->first();

        $returnData = [
            'id' => $req->id,
            'number' => $req->request_number,
            'requester' => $req->user->name ?? '-',
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'return_confirmed_by' => $returnConfirmation?->changer?->name,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department->name ?? '-') 
                : ($req->project->name ?? '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'type' => $req->start_date ? 'peminjaman' : 'permintaan',
            'items' => $items,
            'returnTime' => $returnTimeStr,
            'location' => $returnLocationStr,
            'method' => $returnMethodStr,
        ];

        return Inertia::render('Smart/Admin/ReturnsDetail', [
            'returnId' => $req->id,
            'request' => $returnData,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    /**
     * Mengonfirmasi pengembalian aset dan mengubah status unit kembali tersedia.
     */
    public function confirm(Request $request, $id)
    {
        $req = SmartRequest::findOrFail($id);
        $oldStatus = $req->status;
        $req->update(['status' => 'success']);

        // Return units status back to 'tersedia'
        $requestItems = RequestItem::where('request_id', $req->id)->get();
        foreach ($requestItems as $reqItem) {
            $assignments = RequestUnitAssignment::where('request_item_id', $reqItem->id)->get();
            foreach ($assignments as $asn) {
                $asn->unit->update([
                    'status' => 'tersedia',
                ]);
                $asn->update(['completed_at' => now()]);
            }
        }

        // Set return completed_at
        $ret = RequestReturn::where('request_id', $req->id)->first();
        if ($ret) {
            $ret->update(['completed_at' => now()]);
        }

        RequestStatusLog::create([
            'request_id' => $req->id,
            'status_from' => $oldStatus,
            'status_to' => 'success',
            'changed_by' => $request->user()->id,
            'note' => 'Pengembalian aset dikonfirmasi oleh Admin. Aset telah kembali di gudang.',
        ]);

        return redirect()->route('smart.returns')->with('success', 'Pengembalian aset berhasil dikonfirmasi.');
    }
}
