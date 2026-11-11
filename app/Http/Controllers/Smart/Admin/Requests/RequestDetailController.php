<?php

namespace App\Http\Controllers\Smart\Admin\Requests;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Master\Location;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Models\TbProject;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Unified Request Detail Controller for displaying complete request lifecycle,
 * allocated assets, timeline logs, and processing manual completion actions.
 */
class RequestDetailController extends Controller
{
    /**
     * Display detailed request information, timeline, and allocated items/assets.
     */
    public function show($id)
    {
        $req = SmartRequest::with([
            'user:id,employee_name,email',
            'approver:id,employee_name',
            'approval',
            'approval.approver:id,employee_name',
            'adminConfirmation',
            'adminConfirmation.admin:id,employee_name',
            'handover:id,request_id,method,location,proof_url,scheduled_date,user_confirmed_at',
            'items' => fn($q) => $q->select('id', 'request_id', 'model_id', 'type_id', 'quantity_requested', 'reasoning', 'start_date', 'end_date', 'status'),
            'items.fulfillments:id,request_item_id,asset_id,handover_id,return_id',
            'items.fulfillments.return:id,method,scheduled_date,location,proof_url,completed_at',
            'items.fulfillments.asset:id,number,borrowing_type,status,comp_specification',
            'items.model' => fn($q) => $q->select('id', 'type_id', 'brand_id', 'name', 'specification')->with('lots:id,model_id,image_url'),
            'items.model.brand:id,name',
            'items.model.type:id,category_id,name',
            'items.model.type.category:id,code,name',
            'items.type:id,category_id,name',
            'items.type.category:id,code,name',
            'items.type.models' => fn($q) => $q->select('id', 'type_id', 'name')->with('lots:id,model_id,image_url'),
            'project:id_project,no_project,project_name',
            'department:id,org_name,org_code',
            'statusLogs:id,request_id,status_from,status_to,changed_by,note,created_at',
            'statusLogs.changer:id,employee_name',
            'statusLogs.changer.roles:id,name',
        ])->findOrFail($id);

        $daysLeft = '-';
        $dueDateStr = '-';
        if ($req->end_date) {
            $dueDateStr = $req->end_date->format('d-m-Y H:i');
            $diff = now()->diffInDays($req->end_date, false);
            $daysLeft = $diff >= 0 ? (string)(int)$diff : 'Telat ' . abs((int)$diff) . ' hari';
        }

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $returnConfirmation = $req->statusLogs
            ->where('status_from', 'return')
            ->where('status_to', 'success')
            ->first();

        $logs = $req->statusLogs->map(function ($log) {
            $actorRole = 'User';
            $actorName = $log->changer->name ?? '-';
            if ($log->changer && in_array($log->changer->role, ['admin_cfs', 'admin_ict'], true)) {
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
        })->values()->toArray();

        $items = $req->items->map(function ($item) {
            $assets = $item->fulfillments
                ->pluck('asset.number')
                ->filter()
                ->values()
                ->toArray();

            $allocatedAssets = $item->fulfillments
                ->filter(fn($f) => $f->asset !== null)
                ->map(function ($f) {
                    $isReturned = !empty($f->return_id);
                    $returnedAt = $f->return?->completed_at
                        ? $f->return->completed_at->format('d-m-Y H:i')
                        : null;
                    $proofUrl = $f->return?->proof_url ? '/media/' . ltrim($f->return->proof_url, '/') : null;

                    return [
                        'fulfillment_id' => $f->id,
                        'asset_id' => $f->asset_id,
                        'asset_number' => $f->asset->number,
                        'number' => $f->asset->number,
                        'is_returned' => $isReturned,
                        'returned_at' => $returnedAt,
                        'proof_url' => $proofUrl,
                    ];
                })
                ->values()
                ->toArray();

            $typeName = $item->model?->type?->name ?? $item->type?->name ?? '-';
            $catName = $item->model?->type?->category?->name ?? $item->type?->category?->name ?? '-';
            $catCode = strtoupper((string) ($item->model?->type?->category?->code ?? $item->type?->category?->code ?? ''));
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
                'allocated_assets' => $allocatedAssets,
                'imageUrl' => $imageUrl,
                'is_consumable' => $isConsumable,
                'reasoning' => $item->reasoning,
            ];
        });

        $hasActiveAssets = $req->items->flatMap->fulfillments->whereNull('return_id')->isNotEmpty();
        $locations = Location::with('parent')->active()->orderBy('name')->get();

        $requestData = [
            'id' => $req->id,
            'number' => $req->request_number,
            'form_url' => $req->form_url ? '/media/' . ltrim($req->form_url, '/') : null,
            'handover_proof_url' => $req->handover?->proof_url ? '/media/' . ltrim($req->handover->proof_url, '/') : null,
            'handover_location' => $req->handover?->location,
            'is_manual' => !empty($req->form_url),
            'has_active_assets' => $hasActiveAssets,
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
            'reasoning' => $req->reasoning,
            'durationStart' => $req->start_date ? $req->start_date->format('d-m-Y H:i') : null,
            'durationEnd' => $req->end_date ? $req->end_date->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'status' => $req->status,
            'raw_status' => $req->status,
            'type' => $req->type_key,
            'specific_type' => $req->specific_type_key,
            'items' => $items,
            'dueDate' => $dueDateStr,
            'daysLeft' => $daysLeft,
            'logs' => $logs,
        ];

        return Inertia::render('Smart/Admin/Requests/RequestDetail', [
            'requestId' => $req->id,
            'request' => $requestData,
            'locations' => $locations,
            'projects' => Inertia::lazy(fn () => TbProject::optionsForSelection()),
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    /**
     * Selesaikan seluruh peminjaman/assignment manual (admin-driven) sekaligus.
     * Menggunakan batch update dan transaksi database untuk performa maksimal.
     */
    public function finishAll(Request $request, $id, ImageUploadService $imageUploadService)
    {
        $req = SmartRequest::findOrFail($id);

        if (empty($req->form_url)) {
            abort(403, 'Aksi ini hanya dapat dilakukan untuk peminjaman/assignment manual.');
        }

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $proofPath = $imageUploadService->storeImageOrFile($validated['proof'], 'returns');

        DB::transaction(function () use ($req, $request, $validated, $proofPath) {
            $now = now();
            $oldStatus = $req->status;
            $location = Location::findOrFail($validated['location_id']);
            $locationName = $location->full_name ?? $location->name;

            // 1. Create RequestReturn
            $requestReturn = RequestReturn::create([
                'request_id' => $req->id,
                'method' => 'manual',
                'scheduled_date' => null,
                'location' => $locationName,
                'proof_url' => $proofPath,
                'is_auto_set' => false,
                'completed_at' => $now,
            ]);

            // 2. Identify active fulfillments
            $itemIds = $req->items()->pluck('id');
            $fulfillments = RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('return_id')
                ->get();

            $assetIds = $fulfillments->pluck('asset_id')->filter()->unique();
            if ($assetIds->isNotEmpty()) {
                Asset::whereIn('id', $assetIds)->update([
                    'status' => 'Tersedia',
                    'location_id' => $location->id,
                ]);

                // Close active lifecycles
                AssetLifecycle::whereIn('asset_id', $assetIds)
                    ->whereNull('end_date')
                    ->update(['end_date' => $now]);

                $borrowerUser = $req->user;
                $userName = $borrowerUser?->name ?? '-';
                $userNpk = $borrowerUser?->employee_id ?? '-';
                $actionLabel = match ($req->specific_type_key) {
                    'st' => 'Peminjaman (ST)',
                    'rent' => 'Peminjaman (Sewa)',
                    default => 'Assignment (LT)',
                };
                $lifecycleActionType = in_array($req->specific_type_key, ['st', 'rent'], true) ? 'Pengembalian' : 'Pengembalian Assignment';

                foreach ($fulfillments as $f) {
                    if (!$f->asset_id) continue;
                    $asset = Asset::find($f->asset_id);
                    AssetLifecycle::create([
                        'asset_id' => $f->asset_id,
                        'request_id' => $req->id,
                        'fulfillment_id' => $f->id,
                        'action_type' => $lifecycleActionType,
                        'status' => 'Tersedia',
                        'condition' => $asset?->condition ?? 'Bagus',
                        'location_id' => $location->id,
                        'start_date' => $now,
                        'end_date' => null,
                        'actor_id' => $request->user()->id,
                        'note' => "Pengembalian {$actionLabel} {$req->request_number} dari {$userName} ({$userNpk}).",
                        'previous_state' => ['status' => $asset?->status ?? 'Dipinjam'],
                        'new_state' => ['status' => 'Tersedia'],
                    ]);
                }
            }

            RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('return_id')
                ->update([
                    'return_id' => $requestReturn->id,
                ]);

            $req->update(['status' => 'success']);

            $actionName = ($req->specific_type_key === 'lt') ? 'assignment' : 'peminjaman';
            $adminName = $request->user()->name;

            RequestStatusLog::create([
                'request_id' => $req->id,
                'status_from' => $oldStatus,
                'status_to' => 'success',
                'changed_by' => $request->user()->id,
                'note' => "Seluruh {$actionName} diselesaikan secara manual oleh Admin {$adminName} di {$locationName}.",
            ]);
        });

        return redirect()->back()->with('success', 'Seluruh peminjaman/assignment berhasil diselesaikan.');
    }

    /**
     * Selesaikan peminjaman/assignment aset spesifik secara manual.
     * Jika semua aset telah selesai, permintaan otomatis berstatus 'success'.
     */
    public function finishAsset(Request $request, $id, $fulfillmentId, ImageUploadService $imageUploadService)
    {
        $req = SmartRequest::findOrFail($id);

        if (empty($req->form_url)) {
            abort(403, 'Aksi ini hanya dapat dilakukan untuk peminjaman/assignment manual.');
        }

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $proofPath = $imageUploadService->storeImageOrFile($validated['proof'], 'returns');

        DB::transaction(function () use ($req, $fulfillmentId, $request, $validated, $proofPath) {
            $now = now();
            $location = Location::findOrFail($validated['location_id']);
            $locationName = $location->full_name ?? $location->name;

            // 1. Create RequestReturn
            $requestReturn = RequestReturn::create([
                'request_id' => $req->id,
                'method' => 'manual',
                'scheduled_date' => null,
                'location' => $locationName,
                'proof_url' => $proofPath,
                'is_auto_set' => false,
                'completed_at' => $now,
            ]);

            // 2. Find target fulfillment
            $fulfillment = RequestFulfillment::where('id', $fulfillmentId)
                ->whereHas('requestItem', fn($q) => $q->where('request_id', $req->id))
                ->firstOrFail();

            if ($fulfillment->asset_id) {
                $asset = Asset::findOrFail($fulfillment->asset_id);
                $asset->update([
                    'status' => 'Tersedia',
                    'location_id' => $location->id,
                ]);

                // Close active lifecycle for this asset
                AssetLifecycle::where('asset_id', $asset->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => $now]);

                $borrowerUser = $req->user;
                $userName = $borrowerUser?->name ?? '-';
                $userNpk = $borrowerUser?->employee_id ?? '-';
                $actionLabel = match ($req->specific_type_key) {
                    'st' => 'Peminjaman (ST)',
                    'rent' => 'Peminjaman (Sewa)',
                    default => 'Assignment (LT)',
                };
                $lifecycleActionType = in_array($req->specific_type_key, ['st', 'rent'], true) ? 'Pengembalian' : 'Pengembalian Assignment';

                AssetLifecycle::create([
                    'asset_id' => $asset->id,
                    'request_id' => $req->id,
                    'fulfillment_id' => $fulfillment->id,
                    'action_type' => $lifecycleActionType,
                    'status' => 'Tersedia',
                    'condition' => $asset->condition ?? 'Bagus',
                    'location_id' => $location->id,
                    'start_date' => $now,
                    'end_date' => null,
                    'actor_id' => $request->user()->id,
                    'note' => "Pengembalian unit {$asset->number} ({$actionLabel}) {$req->request_number} dari {$userName} ({$userNpk}).",
                    'previous_state' => ['status' => $asset->status ?? 'Dipinjam'],
                    'new_state' => ['status' => 'Tersedia'],
                ]);
            }

            $fulfillment->update([
                'return_id' => $requestReturn->id,
            ]);

            // Check if all fulfillments have been returned
            $itemIds = $req->items()->pluck('id');
            $remainingActive = RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('return_id')
                ->exists();

            $adminName = $request->user()->name;
            $unitIdentifier = isset($asset) ? "unit {$asset->number}" : "aset";

            if (!$remainingActive) {
                $oldStatus = $req->status;
                $req->update(['status' => 'success']);

                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => $oldStatus,
                    'status_to' => 'success',
                    'changed_by' => $request->user()->id,
                    'note' => "Pengembalian {$unitIdentifier} diselesaikan secara manual oleh Admin {$adminName} di {$locationName}. Seluruh aset telah selesai.",
                ]);
            } else {
                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => $req->status,
                    'status_to' => $req->status,
                    'changed_by' => $request->user()->id,
                    'note' => "Pengembalian {$unitIdentifier} diselesaikan secara manual oleh Admin {$adminName} di {$locationName}.",
                ]);
            }
        });

        return redirect()->back()->with('success', 'Aset berhasil diselesaikan.');
    }
}
