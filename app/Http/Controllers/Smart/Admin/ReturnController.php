<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Models\Request\RequestFulfillment;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Return Controller managing return scheduling, overdue tracking, and warehouse check-in for returned assets.
 */
class ReturnController extends Controller
{
    /**
     * Display the asset return listing page (Returns).
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

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/ActiveRequests', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'activeTab' => 'Pengembalian',
            'returnsList' => $returnsList,
        ]);
    }

    /**
     * Display detailed return information for a specified request.
     */
    public function show($id)
    {
        $req = SmartRequest::with([
            'user',
            'return',
            'approver',
            'approval.approver',
            'adminConfirmation.admin',
            'statusLogs.changer',
            'items.model.type.category',
            'items.model.brand',
            'items.type.category',
            'items.type.models',
        ])
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
            $assets = RequestFulfillment::where('request_item_id', $item->id)
                ->with('asset')
                ->get()
                ->pluck('asset.number')
                ->filter()
                ->values()
                ->toArray();

            $typeName = $item->model?->type?->name ?? $item->type?->name ?? '-';
            $catName = $item->model?->type?->category?->name ?? $item->type?->category?->name ?? '-';
            $isConsumable = (bool) $item->is_consumable;
            $imageUrl = $item->model?->image_url
                ? '/media/' . $item->model->image_url
                : (($firstModel = $item->type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null);
            $brandSpec = trim(($item->model?->brand?->name ?? '') . ' ' . ($item->model?->specification ?? ''));

            return [
                'id' => $item->id,
                'model_id' => $item->model_id,
                'barang_id' => $item->model_id,
                'brand' => $brandSpec ?: '-',
                'name' => $item->model?->name ?? 'Tidak Spesifik',
                'category' => $catName,
                'type' => $typeName,
                'subcategory' => $typeName,
                'quantity' => $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $imageUrl,
                'is_consumable' => $isConsumable,
            ];
        });

        $returnTimeStr = $ret && $ret->scheduled_date ? $ret->scheduled_date->format('d-m-Y H:i') : '-';
        $returnLocationStr = $ret ? $ret->location : '-';
        $returnMethodStr = $ret && $ret->method === 'self' ? 'Kembalikan sendiri' : ($ret ? 'Diantar ke GA / IT Support' : 'Belum diatur');

        $returnConfirmation = $req->statusLogs
            ->where('status_from', 'return')
            ->where('status_to', 'success')
            ->first();

        $logs = $req->statusLogs->map(function ($log) {
            $actorRole = 'User';
            $actorName = $log->changer->name ?? '-';
            if ($log->changer && $log->changer->role === 'admin') {
                $actorRole = 'Admin';
            } else if ($log->changer && in_array($log->changer->role, ['manager', 'ifs_manager'])) {
                $actorRole = 'Manager';
            }

            return [
                'id' => $log->id,
                'status_from' => $log->status_from,
                'status_to' => $log->status_to,
                'time' => $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-',
                'actor' => "{$actorRole}: {$actorName}",
                'user' => $actorName,
                'note' => $log->note ?? '',
            ];
        })->toArray();

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
                ? ($req->department?->org_name ?? $req->department?->name ?? '-') 
                : ($req->project ? ($req->project->no_project ? "{$req->project->no_project} ({$req->project->project_name})" : ($req->project->project_name ?? '-')) : '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'type' => $req->type_key,
            'items' => $items,
            'returnTime' => $returnTimeStr,
            'location' => $returnLocationStr,
            'method' => $returnMethodStr,
            'logs' => $logs,
        ];

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/Details/ReturnsDetail', [
            'returnId' => $req->id,
            'request' => $returnData,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    /**
     * Mengonfirmasi pengembalian aset dan mengubah status asset kembali tersedia.
     */
    public function confirm(Request $request, $id)
    {
        $req = SmartRequest::findOrFail($id);
        $oldStatus = $req->status;
        $req->update(['status' => 'success']);

        // Return assets status back to 'tersedia'
        $requestItems = RequestItem::where('request_id', $req->id)->get();
        foreach ($requestItems as $reqItem) {
            $assignments = RequestFulfillment::where('request_item_id', $reqItem->id)->get();
            foreach ($assignments as $asn) {
                if ($asn->asset) {
                    $asn->asset->update([
                        'status' => 'Tersedia',
                    ]);
                }
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

        return redirect()->route('smart.returns')->with('success', __('requests.return_confirmed'));
    }
}
