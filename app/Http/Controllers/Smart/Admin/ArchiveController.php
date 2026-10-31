<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Archive Controller providing searchable history and full lifecycle logs of fulfilled, rejected, and cancelled requests.
 */
class ArchiveController extends Controller
{
    /**
     * Display the request/borrow archive listing page (Arsip).
     */
    public function index()
    {
        $archiveList = SmartRequest::with([
            'user:id,employee_name,employee_id',
            'statusLogs:id,request_id,status_from,status_to,note',
            'items' => fn($q) => $q->select('id', 'request_id', 'start_date', 'end_date'),
            'items.fulfillments' => fn($q) => $q->select('id', 'request_item_id', 'asset_id'),
            'items.fulfillments.asset:id,borrowing_type,status',
        ])
            ->whereIn('status', ['success', 'reject', 'cancel'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                $type = $req->specific_type_key;
                
                $statusStr = 'Sukses';
                if ($req->status === 'reject') {
                    $statusStr = 'Ditolak';
                } elseif ($req->status === 'cancel') {
                    $statusStr = 'Dibatalkan';
                }

                $timeStr = $req->start_date ? $req->start_date->format('d-m-Y H:i') : ($req->created_at ? $req->created_at->format('d-m-Y H:i') : '-');
                $endTimeStr = $req->end_date ? $req->end_date->format('d-m-Y H:i') : '-';

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'type' => $type,
                    'status' => $statusStr,
                    'requester' => $req->user->name ?? '-',
                    'startTime' => $timeStr,
                    'endTime' => $endTimeStr,
                ];
            });

        return Inertia::render('Smart/Admin/Requests/Archive/ArchiveList', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'archiveList' => $archiveList,
        ]);
    }

    /**
     * Display detailed archive information for a specified request.
     */
    public function show($id)
    {
        $req = SmartRequest::with([
            'user',
            'approver',
            'approval.approver',
            'adminConfirmation.admin',
            'statusLogs.changer',
            'items.model.type.category',
            'items.model.brand',
            'items.type.category',
            'items.type.models',
            'items.fulfillments.asset',
            'project',
            'department'
        ])
            ->findOrFail($id);

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $items = $req->items->map(function ($item) {
            $assets = $item->fulfillments
                ->pluck('asset.number')
                ->filter()
                ->values()
                ->toArray();

            $catCode = $item->model?->type?->category?->code ?? $item->type?->category?->code ?? '';
            $catName = $item->model?->type?->category?->name ?? $item->type?->category?->name ?? '-';
            $typeName = $item->model?->type?->name ?? $item->type?->name ?? '-';
            $isConsumable = (bool) $item->is_consumable;
            $imageUrl = $item->model?->image_url
                ? '/media/' . $item->model->image_url
                : (($firstModel = $item->type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null);
            $brandName = $item->model?->brand?->name ?? '-';

            $isComp = $catCode === 'COMP' || strtolower($catName) === 'computer' || strtolower($catName) === 'komputer';
            $compSpec = null;
            if ($isComp) {
                $compSpec = $item->fulfillments
                    ->pluck('asset.comp_specification')
                    ->filter()
                    ->first();
            }
            $spec = $compSpec ?: ($item->model?->specification ?? '');

            return [
                'id' => $item->id,
                'model_id' => $item->model_id,
                'barang_id' => $item->model_id,
                'brand' => $brandName,
                'name' => $item->model?->name ?? 'Tidak Spesifik',
                'spec' => $spec,
                'category' => $catName,
                'category_code' => $catCode,
                'type' => $typeName,
                'subcategory' => $typeName,
                'quantity' => (int) $item->quantity_requested,
                'assets' => $assets,
                'imageUrl' => $imageUrl,
                'is_consumable' => $isConsumable,
                'reasoning' => $item->reasoning,
            ];
        });

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

        $mappedRequest = [
            'id' => $req->id,
            'number' => $req->request_number,
            'form_url' => $req->form_url ? '/media/' . ltrim($req->form_url, '/') : null,
            'is_manual' => !empty($req->form_url),
            'requester' => $req->user->name ?? '-',
            'requester_department' => $req->department?->org_name ?? $req->department?->name ?? null,
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'created_by_admin' => $req->adminConfirmation?->admin?->name,
            'return_confirmed_by' => $returnConfirmation?->changer?->name,
            'createdAt' => $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-',
            'updatedAt' => $req->updated_at ? $req->updated_at->format('d-m-Y H:i') : '-',
            'pemanfaatan' => $req->utilization,
            'pemanfaatanDetail' => $req->utilization === 'corporate' 
                ? ($req->department?->org_name ?? $req->department?->name ?? '-') 
                : ($req->project ? ($req->project->no_project ? "{$req->project->no_project} ({$req->project->project_name})" : ($req->project->project_name ?? '-')) : '-'),
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'raw_status' => $req->status,
            'type' => $req->type_key,
            'specific_type' => $req->specific_type_key,
            'items' => $items,
            'logs' => $logs,
        ];

        return Inertia::render('Smart/Admin/Requests/Archive/ArchiveDetail', [
            'requestId' => $req->id,
            'request' => $mappedRequest,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }
}
