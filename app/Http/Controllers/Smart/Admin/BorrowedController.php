<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Master\Location;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestReturn;
use App\Models\Request\RequestStatusLog;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Borrowed Controller tracking active asset loans, due dates, borrow durations, and current placements.
 */
class BorrowedController extends Controller
{
    /**
     * Display active borrowings tracking page (Lacak Peminjaman).
     */
    public function index()
    {
        $borrowedList = SmartRequest::with([
            'user:id,employee_name,employee_id',
            'handover:id,request_id,user_confirmed_at',
            'statusLogs:id,request_id,status_from,status_to,note',
            'items' => fn($q) => $q->select('id', 'request_id', 'start_date', 'end_date'),
            'items.fulfillments' => fn($q) => $q->select('id', 'request_item_id', 'asset_id'),
            'items.fulfillments.asset:id,borrowing_type,status',
        ])
            ->where('status', 'borrow')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                // Calculate days left
                $daysLeft = '-';
                $dueDateStr = '-';
                if ($req->end_date) {
                    $dueDateStr = $req->end_date->format('d-m-Y H:i');
                    $diff = now()->diffInDays($req->end_date, false);
                    $daysLeft = $diff >= 0 ? (string)(int)$diff : 'Telat ' . abs((int)$diff) . ' hari';
                }

                // Calculate days passed since the goods were taken
                $daysPassed = '-';
                $confirmedAt = $req->handover?->user_confirmed_at ?? $req->updated_at;
                if ($confirmedAt) {
                    $diffPassed = $confirmedAt->diffInDays(now(), false);
                    $daysPassedVal = max(0, (int) $diffPassed);
                    $daysPassed = (string)$daysPassedVal . ' hari';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'type' => $req->specific_type_key,
                    'borrower' => $req->user->name ?? '-',
                    'dueDate' => $dueDateStr,
                    'daysLeft' => $daysLeft,
                    'daysPassed' => $daysPassed,
                ];
            });

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/ActiveRequests', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'activeTab' => 'Lacak Peminjaman',
            'borrowedList' => $borrowedList,
        ]);
    }

    /**
     * Display detailed active loan information for a specified request.
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
            'items' => fn($q) => $q->select('id', 'request_id', 'model_id', 'type_id', 'quantity_requested', 'reasoning', 'start_date', 'end_date', 'status'),
            'items.fulfillments:id,request_item_id,asset_id,return_id,completed_at',
            'items.fulfillments.return:id,method,scheduled_date,location,proof_url,completed_at',
            'items.fulfillments.asset:id,number,comp_specification',
            'items.model' => fn($q) => $q->select('id', 'type_id', 'brand_id', 'name', 'specification')->with('lots:id,model_id,image_url'),
            'items.model.brand:id,name',
            'items.model.type:id,category_id,name',
            'items.model.type.category:id,code,name',
            'items.type:id,category_id,name',
            'items.type.category:id,code,name',
            'items.type.models' => fn($q) => $q->select('id', 'type_id', 'name')->with('lots:id,model_id,image_url'),
            'project:id,no_project,project_name',
            'department:id,org_name,org_code',
            'statusLogs:id,request_id,status_from,status_to,changed_by,note,created_at',
            'statusLogs.changer:id,employee_name',
            'statusLogs.changer.roles:id,name',
        ])
            ->findOrFail($id);

        $daysLeft = '-';
        $dueDateStr = '-';
        if ($req->end_date) {
            $dueDateStr = $req->end_date->format('d-m-Y H:i');
            $diff = now()->diffInDays($req->end_date, false);
            $daysLeft = $diff >= 0 ? (string)$diff : 'Telat ' . abs($diff) . ' hari';
        }

        $durationDays = 0;
        $durationHours = 0;
        if ($req->start_date && $req->end_date) {
            $diff = $req->start_date->diff($req->end_date);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

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
                        : ($f->completed_at ? $f->completed_at->format('d-m-Y H:i') : null);
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

            // For COMP category, retrieve unit-level comp_specification from fulfilled assets if available
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

        $borrowedData = [
            'id' => $req->id,
            'number' => $req->request_number,
            'form_url' => $req->form_url ? '/media/' . ltrim($req->form_url, '/') : null,
            'is_manual' => !empty($req->form_url),
            'has_active_assets' => $hasActiveAssets,
            'requester' => $req->user->name ?? '-',
            'approver' => $req->approver->name ?? '-',
            'approval_by' => $req->approval?->approver?->name,
            'confirmation_by' => $req->adminConfirmation?->admin?->name,
            'created_by_admin' => $req->adminConfirmation?->admin?->name,
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
            'raw_status' => $req->status,
            'type' => $req->type_key,
            'specific_type' => $req->specific_type_key,
            'items' => $items,
            'dueDate' => $dueDateStr,
            'daysLeft' => $daysLeft,
            'logs' => $logs,
        ];

        return Inertia::render('Smart/Admin/Requests/ActiveRequests/Details/BorrowedDetail', [
            'borrowedId' => $req->id,
            'request' => $borrowedData,
            'locations' => $locations,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    /**
     * Selesaikan seluruh peminjaman/penugasan manual (admin-driven) sekaligus.
     * Menggunakan batch update dan transaksi database untuk performa maksimal.
     */
    public function finishAll(Request $request, $id, ImageUploadService $imageUploadService)
    {
        $req = SmartRequest::findOrFail($id);

        if (empty($req->form_url)) {
            abort(403, 'Aksi ini hanya dapat dilakukan untuk peminjaman/penugasan manual.');
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
            }

            RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('return_id')
                ->update([
                    'return_id' => $requestReturn->id,
                    'completed_at' => $now,
                ]);

            $req->update(['status' => 'success']);

            $actionName = ($req->specific_type_key === 'lt') ? 'penugasan' : 'peminjaman';
            $adminName = $request->user()->name;

            RequestStatusLog::create([
                'request_id' => $req->id,
                'status_from' => $oldStatus,
                'status_to' => 'success',
                'changed_by' => $request->user()->id,
                'note' => "Seluruh {$actionName} diselesaikan secara manual oleh Admin {$adminName} di {$locationName}.",
            ]);
        });

        return redirect()->back()->with('success', 'Seluruh peminjaman/penugasan berhasil diselesaikan.');
    }

    /**
     * Selesaikan peminjaman/penugasan aset spesifik secara manual.
     * Jika semua aset telah selesai, permintaan otomatis berstatus 'success'.
     */
    public function finishAsset(Request $request, $id, $fulfillmentId, ImageUploadService $imageUploadService)
    {
        $req = SmartRequest::findOrFail($id);

        if (empty($req->form_url)) {
            abort(403, 'Aksi ini hanya dapat dilakukan untuk peminjaman/penugasan manual.');
        }

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $proofPath = $imageUploadService->storeImageOrFile($validated['proof'], 'returns');

        DB::transaction(function () use ($req, $request, $fulfillmentId, $validated, $proofPath) {
            $itemIds = $req->items()->pluck('id');
            $fulfillment = RequestFulfillment::where('id', $fulfillmentId)
                ->whereIn('request_item_id', $itemIds)
                ->whereNull('return_id')
                ->firstOrFail();

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

            $fulfillment->update([
                'return_id' => $requestReturn->id,
                'completed_at' => $now,
            ]);

            $assetNumber = 'Aset';
            if ($fulfillment->asset_id) {
                $asset = Asset::find($fulfillment->asset_id);
                if ($asset) {
                    $asset->update([
                        'status' => 'Tersedia',
                        'location_id' => $location->id,
                    ]);
                    $assetNumber = $asset->number;
                }
            }

            $hasRemaining = RequestFulfillment::whereIn('request_item_id', $itemIds)
                ->whereNull('return_id')
                ->exists();

            $actionPrefix = ($req->specific_type_key === 'lt') ? 'Penugasan' : 'Peminjaman';
            $adminName = $request->user()->name;
            $oldStatus = $req->status;

            if (!$hasRemaining) {
                // 1. Log asset-specific completion
                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => $oldStatus,
                    'status_to' => $oldStatus,
                    'changed_by' => $request->user()->id,
                    'note' => "{$actionPrefix} aset {$assetNumber} diselesaikan oleh Admin {$adminName} di {$locationName}.",
                ]);

                // 2. Advance whole request status to success and log complete finish
                $req->update(['status' => 'success']);

                $actionName = ($req->specific_type_key === 'lt') ? 'penugasan' : 'peminjaman';

                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => $oldStatus,
                    'status_to' => 'success',
                    'changed_by' => $request->user()->id,
                    'note' => "Seluruh {$actionName} diselesaikan secara manual oleh Admin {$adminName} di {$locationName}.",
                ]);
            } else {
                RequestStatusLog::create([
                    'request_id' => $req->id,
                    'status_from' => $oldStatus,
                    'status_to' => $oldStatus,
                    'changed_by' => $request->user()->id,
                    'note' => "{$actionPrefix} aset {$assetNumber} diselesaikan oleh Admin {$adminName} di {$locationName}.",
                ]);
            }
        });

        return redirect()->back()->with('success', 'Peminjaman/penugasan aset berhasil diselesaikan.');
    }
}
