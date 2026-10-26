<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Services\Inventory\AssetNumberService;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Bulk Asset Controller managing automated multi-asset code generation, mass creation, and batch status updates.
 */
class BulkAssetController extends Controller
{
    /**
     * Store newly created asset units in bulk in storage.
     */
    public function store(Request $request)
    {
        $borrowingType = $request->input('borrowing_type', $request->input('type'));
        if ($borrowingType !== null) {
            $request->merge(['borrowing_type' => $borrowingType]);
        }

        $rules = [
            'number' => 'nullable|string|max:50',
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'status' => 'nullable|string|max:255',
            'condition' => 'required|string|max:255',
            'borrowing_type' => 'required|string|in:LT,ST',
            'classification' => 'required|string|in:Aset,Inventaris',
            'comp_specification' => 'nullable|string|max:255',
            'specification' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024',
            'use_lot_image' => 'nullable',
            'bulk_quantity' => 'required|integer|min:1|max:999',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => 'required_if:burden,Project|nullable|integer',
        ];

        $lot = Lot::with('model.type.category')->findOrFail($request->input('lot_id'));
        $isVehicle = false;
        if ($lot->model && $lot->model->type && $lot->model->type->category) {
            $catName = strtolower($lot->model->type->category->name);
            $typeName = strtolower($lot->model->type->name);
            $isVehicle = str_contains($catName, 'kendaraan') || str_contains($typeName, 'kendaraan') ||
                         str_contains($catName, 'mobil') || str_contains($typeName, 'mobil') ||
                         str_contains($catName, 'motor') || str_contains($typeName, 'motor');
        }

        if ($isVehicle) {
            $rules['vehicle_registration'] = 'required|string|max:15';
        } else {
            $rules['vehicle_registration'] = 'nullable|string|max:15';
        }

        $arrNeedApproval = ['Rusak Total', 'Hilang'];
        $proposedCondition = $request->input('condition');
        $needApproval = in_array($proposedCondition, $arrNeedApproval);

        if ($needApproval) {
            $rules['memo_file'] = 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048';
            $rules['admin_note'] = 'nullable|string';
            if ($proposedCondition === 'Hilang') {
                $rules['lost_doc_file'] = 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048';
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, [
            'bulk_quantity.integer' => 'Tidak boleh desimal.',
        ]);
        if ($validator->fails()) {
            if ($validator->errors()->has('borrowing_type')) {
                $validator->errors()->add('type', $validator->errors()->first('borrowing_type'));
            }
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        $validated = $validator->validated();

        $isComp = ($lot->model?->type?->category?->code === 'COMP');
        $compSpec = $request->input('comp_specification', $request->input('specification'));
        $finalCompSpec = $isComp ? $compSpec : null;

        if ($needApproval) {
            $validated['status'] = 'Tersedia,Pending:DM';
        } else {
            $validated['status'] = 'Belum Diverifikasi';
        }

        $quantity = (int)$validated['bulk_quantity'];
        $generatedNumbers = app(AssetNumberService::class)->generateBulkAssetNumbers(
            $lot,
            $quantity,
            $validated['number'] ?? null
        );

        $existing = Asset::whereIn('number', $generatedNumbers)->pluck('number')->toArray();
        if (!empty($existing)) {
            return redirect()->back()->withErrors(['number' => 'Beberapa Nomor Aset sudah terpakai: ' . implode(', ', $existing)]);
        }

        $imageUploadService = app(\App\Services\ImageUploadService::class);

        $finalImagePath = null;
        if ($request->boolean('use_lot_image')) {
            if ($lot->image_url && Storage::disk('local')->exists($lot->image_url)) {
                $finalImagePath = $lot->image_url;
            } else {
                $finalImagePath = null;
            }
        } else if ($request->hasFile('image_url')) {
            $finalImagePath = $imageUploadService->storeAsWebp($request->file('image_url'), 'inventory');
        }

        $classification = $validated['classification'];
        $burden = $validated['burden'] ?? 'Corporate';
        $projectId = $burden === 'Project' ? ($validated['project_id'] ?? null) : null;

        $createdAssets = DB::transaction(function () use (
            $generatedNumbers,
            $validated,
            $borrowingType,
            $classification,
            $finalCompSpec,
            $finalImagePath,
            $burden,
            $projectId,
            $needApproval,
            $proposedCondition,
            $request,
            $imageUploadService
        ) {
            $memoUrl = null;
            $lostDocUrl = null;
            if ($needApproval) {
                $memoUrl = 'memos/placeholder.pdf';
                if ($request->hasFile('memo_file')) {
                    $memoUrl = $imageUploadService->storeImageOrFile($request->file('memo_file'), 'memos');
                }
                if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
                    $lostDocUrl = $imageUploadService->storeImageOrFile($request->file('lost_doc_file'), 'lost_docs');
                }
            }

            $assets = [];
            foreach ($generatedNumbers as $num) {
                $asset = Asset::create([
                    'number' => $num,
                    'lot_id' => $validated['lot_id'],
                    'location_id' => $validated['location_id'],
                    'status' => $validated['status'],
                    'condition' => $validated['condition'],
                    'borrowing_type' => $borrowingType,
                    'classification' => $classification,
                    'comp_specification' => $finalCompSpec,
                    'price' => $validated['price'] ?? null,
                    'image_url' => $finalImagePath,
                    'vehicle_registration' => $validated['vehicle_registration'] ?? null,
                    'burden' => $burden,
                    'project_id' => $projectId,
                ]);

                if ($needApproval) {
                    AssetDeletionApproval::create([
                        'asset_id' => $asset->id,
                        'requester_id' => $request->user()->id,
                        'proposed_condition' => $proposedCondition,
                        'previous_condition' => 'Bagus',
                        'previous_status' => 'Tersedia',
                        'decision' => 'pending',
                        'admin_note' => $request->input('admin_note'),
                        'dm_note' => null,
                        'approver_id' => null,
                        'requested_at' => now(),
                        'memo_url' => $memoUrl,
                        'lost_doc_url' => $lostDocUrl,
                    ]);
                } elseif ($asset->status === 'Belum Diverifikasi') {
                    AssetActivationApproval::create([
                        'asset_id' => $asset->id,
                        'requester_id' => $request->user()->id,
                        'decision' => 'pending',
                        'requested_at' => now(),
                    ]);
                }

                $assets[] = $asset;
            }

            return $assets;
        });

        if (!empty($createdAssets)) {
            if ($needApproval) {
                $user = $request->user();
                $proposedCondition = $validated['condition'] ?? 'Rusak Total';
                dispatch(function () use ($createdAssets, $proposedCondition, $user) {
                    $notificationService = app(\App\Services\NotificationService::class);
                    if (count($createdAssets) === 1) {
                        $notificationService->notifyManagerAssetDeletionChange($createdAssets[0]);
                    } else {
                        $notificationService->notifyManagerBulkAssetDeletionChange($createdAssets, $proposedCondition, $user);
                    }
                })->afterResponse();
            } elseif ($validated['status'] === 'Belum Diverifikasi') {
                $user = $request->user();
                dispatch(function () use ($createdAssets, $lot, $user) {
                    app(\App\Services\NotificationService::class)->notifyManagerBulkAssetActivation($createdAssets, $lot, $user);
                })->afterResponse();
            }
        }

        return redirect()->back()->with('success', __('inventory.bulk_create_success', ['count' => $quantity]));
    }

    /**
     * Bulk update attributes and condition/status of multiple asset units in storage.
     */
    public function update(Request $request)
    {
        $messages = [
            'ids.required' => 'Aset belum dipilih.',
            'status.in' => 'Status yang dipilih tidak valid.',
            'condition.in' => 'Kondisi yang dipilih tidak valid.',
            'location_id.exists' => 'Lokasi tidak ditemukan.',
            'price.numeric' => 'Harga Satuan harus berupa angka.',
            'price.min' => 'Harga Satuan minimal 0.',
            'price.max' => 'Harga Satuan terlalu besar.',
            'image_url.image' => 'Format foto salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png.',
            'image_url.mimes' => 'Format foto salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png.',
            'image_url.max' => 'Gagal! Ukuran foto maksimal 1MB.',
        ];

        if ($request->filled('type') && !$request->filled('borrowing_type')) {
            $request->merge(['borrowing_type' => $request->input('type')]);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:assets,id',
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'Tersedia',
                    'Dipinjam',
                    'Tidak Aktif',
                    'Dihapus',
                    'Pending',
                    'Pending:DM',
                    'Pending:BoD/BoC',
                    'Tersedia,Pending:DM',
                    'Tidak Aktif,Pending:BoD/BoC',
                    'Di-assign (LT)',
                ]),
            ],
            'condition' => ['nullable', 'string', 'in:Bagus,Rusak,Rusak Total,Hilang'],
            'borrowing_type' => ['nullable', 'string', 'in:LT,ST'],
            'type' => ['nullable', 'string', 'in:LT,ST'],
            'classification' => ['nullable', 'string', 'in:Aset,Inventaris'],
            'comp_specification' => 'nullable|string|max:255',
            'specification' => 'nullable|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'use_lot_image' => 'nullable',
            'image_url' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => 'required_if:burden,Project|nullable|integer',
            'is_assigned_to_location' => ['nullable', 'boolean'],
        ], $messages);
        if ($validator->fails()) {
            if ($validator->errors()->has('borrowing_type')) {
                $validator->errors()->add('type', $validator->errors()->first('borrowing_type'));
            }
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        $validated = $validator->validated();

        $ids = $validated['ids'];
        $assets = Asset::whereIn('id', $ids)->get();
        if ($assets->isEmpty()) {
            return redirect()->back()->withErrors(['ids' => 'Tidak ada aset yang ditemukan.']);
        }

        $allPendingBodBoc = $assets->every(fn($u) => $u->hasStatus('Pending:BoD/BoC'));
        if ($allPendingBodBoc && ($request->hasFile('bod_boc_approval_file') || $request->hasFile('bod_boc_disapproval_file'))) {
            if ($request->hasFile('bod_boc_approval_file') && $request->hasFile('bod_boc_disapproval_file')) {
                return redirect()->back()->withErrors([
                    'bod_boc_approval_file' => 'Tidak dapat mengunggah dokumen approval dan disapproval secara bersamaan.',
                    'bod_boc_disapproval_file' => 'Tidak dapat mengunggah dokumen approval dan disapproval secara bersamaan.',
                ]);
            }

            $isDisapproval = $request->hasFile('bod_boc_disapproval_file');
            $fieldName = $isDisapproval ? 'bod_boc_disapproval_file' : 'bod_boc_approval_file';
            $fileLabel = $isDisapproval ? 'Formulir Penolakan BoD/BoC' : 'Formulir Approval BoD/BoC';

            $request->validate([
                $fieldName => 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048',
            ], [
                "{$fieldName}.required" => "{$fileLabel} belum dipilih.",
                "{$fieldName}.mimes" => "Format {$fileLabel} salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.",
                "{$fieldName}.max" => "Gagal! Ukuran {$fileLabel} maksimal 2MB.",
            ]);

            DB::transaction(function () use ($assets, $ids, $request, $isDisapproval, $fieldName) {
                $folder = $isDisapproval ? 'bod_boc_disapprovals' : 'bod_boc_approvals';
                $bodBocUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file($fieldName), $folder);

                $approvalsByAsset = AssetDeletionApproval::whereIn('asset_id', $ids)
                    ->where('decision', 'approved')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->unique('asset_id')
                    ->keyBy('asset_id');

                if ($isDisapproval) {
                    $pastDisapprovals = AssetDeletionApproval::whereIn('asset_id', $ids)
                        ->whereNotNull('bod_boc_disapproval_url')
                        ->get();
                    $activeApprovalIds = $approvalsByAsset->pluck('id')->all();
                    foreach ($pastDisapprovals as $past) {
                        if ($past->bod_boc_disapproval_url && Storage::disk('local')->exists($past->bod_boc_disapproval_url)) {
                            Storage::disk('local')->delete($past->bod_boc_disapproval_url);
                        }
                        if (!in_array($past->id, $activeApprovalIds, true)) {
                            $past->update(['bod_boc_disapproval_url' => null]);
                        }
                    }
                } else {
                    $pastApprovals = AssetDeletionApproval::whereIn('asset_id', $ids)
                        ->whereNotNull('bod_boc_approval_url')
                        ->get();
                    $activeApprovalIds = $approvalsByAsset->pluck('id')->all();
                    foreach ($pastApprovals as $past) {
                        if ($past->bod_boc_approval_url && Storage::disk('local')->exists($past->bod_boc_approval_url)) {
                            Storage::disk('local')->delete($past->bod_boc_approval_url);
                        }
                        if (!in_array($past->id, $activeApprovalIds, true)) {
                            $past->update(['bod_boc_approval_url' => null]);
                        }
                    }
                }

                if ($approvalsByAsset->isNotEmpty()) {
                    $updatePayload = $isDisapproval
                        ? ['bod_boc_disapproval_url' => $bodBocUrl, 'decision' => 'rejected']
                        : ['bod_boc_approval_url' => $bodBocUrl];

                    AssetDeletionApproval::whereIn('id', $approvalsByAsset->pluck('id')->all())
                        ->update($updatePayload);
                }

                $now = now();
                $actorId = $request->user()->id;

                if ($isDisapproval) {
                    foreach ($assets as $asset) {
                        $approval = $approvalsByAsset->get($asset->id);
                        $targetStatus = $approval?->previous_status ?? 'Tersedia';
                        $targetCondition = $approval?->previous_condition ?? 'Bagus';
                        $proposedCondition = $approval?->proposed_condition ?? $asset->condition;

                        Asset::where('id', $asset->id)->update([
                            'status' => $targetStatus,
                            'condition' => $targetCondition,
                        ]);

                        AssetLifecycle::where('asset_id', $asset->id)
                            ->whereNull('end_date')
                            ->update(['end_date' => $now]);

                        AssetLifecycle::create([
                            'asset_id' => $asset->id,
                            'action_type' => 'Penolakan',
                            'status' => $targetStatus,
                            'condition' => $targetCondition,
                            'location_id' => $asset->location_id,
                            'start_date' => $now,
                            'end_date' => null,
                            'actor_id' => $actorId,
                            'note' => "BoD/BoC menolak penghapusan asset dengan kondisi {$proposedCondition}",
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } else {
                    Asset::whereIn('id', $ids)->update(['status' => 'Dihapus']);

                    AssetLifecycle::whereIn('asset_id', $ids)
                        ->whereNull('end_date')
                        ->update(['end_date' => $now]);

                    $lifecycleRows = $assets->map(function (Asset $asset) use ($approvalsByAsset, $now, $actorId) {
                        $approval = $approvalsByAsset->get($asset->id);
                        $proposedCondition = $approval?->proposed_condition ?? $asset->condition;

                        return [
                            'asset_id' => $asset->id,
                            'action_type' => 'Persetujuan',
                            'status' => 'Dihapus',
                            'condition' => $asset->condition,
                            'location_id' => $asset->location_id,
                            'start_date' => $now,
                            'end_date' => null,
                            'actor_id' => $actorId,
                            'note' => "BoD/BoC menyetujui penghapusan asset dengan kondisi {$proposedCondition}",
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    AssetLifecycle::insert($lifecycleRows);
                }
            });

            $successMsg = $isDisapproval
                ? __('inventory.bulk_bod_boc_disapproved', ['count' => count($assets)])
                : __('inventory.bulk_bod_boc_approved', ['count' => count($assets)]);

            return redirect()->back()->with('success', $successMsg);
        }

        $arrInactiveConditions = ['Rusak Total', 'Hilang'];
        $hasRestrictedAssets = $assets->contains(function (Asset $u) use ($arrInactiveConditions) {
            return collect($u->statuses)->contains(fn ($s) => in_array(strtolower($s), ['tidak aktif', 'dihapus', 'pending', 'pending:dm', 'pending:bod/boc']) || str_starts_with(strtolower($s), 'pending'))
                || in_array($u->condition, $arrInactiveConditions);
        });

        if ($hasRestrictedAssets) {
            if ($request->filled('status') || $request->filled('condition')) {
                return redirect()->back()->withErrors(['status' => 'Terdapat aset dengan status Tidak Aktif atau Pending. Status dan kondisi aset tersebut tidak dapat diubah.']);
            }
        } else {
            if ($request->filled('status')) {
                $hasDisallowedStatusAssets = $assets->contains(function ($u) {
                    $s = strtolower(trim($u->status ?? ''));
                    return $s !== 'tersedia' && $s !== 'di-assign (lt)';
                });
                if ($hasDisallowedStatusAssets) {
                    return redirect()->back()->withErrors(['status' => 'Status hanya dapat diubah jika semua aset yang dipilih berstatus Tersedia.']);
                }
            }
            $inputStatusLower = strtolower(trim($request->input('status', '')));
            $proposedCondition = $request->input('condition');
            if ($inputStatusLower === 'tidak aktif' && !in_array($proposedCondition, $arrInactiveConditions)) {
                return redirect()->back()->withErrors(['status' => 'Status Tidak Aktif tidak dapat dipilih secara manual.']);
            }
        }

        $arrNeedApproval = ['Rusak Total', 'Hilang'];
        $proposedCondition = $request->input('condition');
        $needApproval = $request->filled('condition') && in_array($proposedCondition, $arrNeedApproval);

        if ($needApproval && !$hasRestrictedAssets) {
            $rulesForApproval = [
                'memo_file' => 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048',
                'admin_note' => 'nullable|string',
            ];
            $messagesForApproval = [
                'memo_file.required' => 'Berita Acara / Memo wajib diisi jika kondisi Hilang atau Rusak Total.',
                'memo_file.mimes' => 'Format Berita Acara / Memo salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.',
                'memo_file.max' => 'Gagal! Ukuran Berita Acara / Memo maksimal 2MB.',
            ];
            if ($proposedCondition === 'Hilang') {
                $rulesForApproval['lost_doc_file'] = 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048';
                $messagesForApproval['lost_doc_file.required'] = 'Surat Keterangan Kehilangan wajib diisi jika kondisi Hilang.';
                $messagesForApproval['lost_doc_file.mimes'] = 'Format Surat Keterangan Kehilangan salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.';
                $messagesForApproval['lost_doc_file.max'] = 'Gagal! Ukuran Surat Keterangan Kehilangan maksimal 2MB.';
            }
            $request->validate($rulesForApproval, $messagesForApproval);
        }

        $updateData = [];

        // 1. Status & Condition
        if ($needApproval) {
            $updateData['status'] = 'Tersedia,Pending:DM';
        } else if ($request->filled('status')) {
            $updateData['status'] = $request->input('status');
        }

        if ($request->filled('condition')) {
            $updateData['condition'] = $request->input('condition');
        }

        // 2. Location
        if ($request->filled('location_id')) {
            $updateData['location_id'] = $request->input('location_id');
        }

        // 3. Price
        if ($request->has('price')) {
            $updateData['price'] = $request->input('price') !== null ? (float)$request->input('price') : null;
        }

        // 4. Borrowing Type & Classification
        $bType = $request->input('borrowing_type', $request->input('type'));
        if (!empty($bType)) {
            $updateData['borrowing_type'] = $bType;
        }
        if ($request->filled('classification')) {
            $updateData['classification'] = $request->input('classification');
        }
        if ($request->filled('burden')) {
            $updateData['burden'] = $request->input('burden');
            $updateData['project_id'] = $request->input('burden') === 'Project' ? $request->input('project_id') : null;
        }

        // 5. Image URL / Use LOT Image
        $finalImagePath = null;
        $hasNewImage = false;

        if ($request->boolean('use_lot_image')) {
            $lot = Lot::findOrFail($assets->first()->lot_id);
            if ($lot->image_url && Storage::disk('local')->exists($lot->image_url)) {
                $finalImagePath = $lot->image_url;
                $hasNewImage = true;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $finalImagePath = app(\App\Services\ImageUploadService::class)->storeAsWebp($request->file('image_url'), 'inventory');
            $hasNewImage = true;
        }

        if ($hasNewImage) {
            $updateData['image_url'] = $finalImagePath;

            // Delete old images for each asset if not shared
            $oldImages = $assets->pluck('image_url')->filter()->unique();
            foreach ($oldImages as $oldImg) {
                if (Storage::disk('local')->exists($oldImg)) {
                    $isShared = Asset::where('image_url', $oldImg)->whereNotIn('id', $ids)->exists();
                    if (!$isShared) {
                        Storage::disk('local')->delete($oldImg);
                    }
                }
            }
        }

        if ($request->has('comp_specification') || $request->has('specification')) {
            $firstLot = Lot::with('model.type.category')->find($assets->first()?->lot_id);
            if ($firstLot?->model?->type?->category?->code === 'COMP') {
                $updateData['comp_specification'] = $request->input('comp_specification', $request->input('specification'));
            }
        }

        if ($request->has('is_assigned_to_location') && $request->input('is_assigned_to_location') !== null) {
            $isAssigned = $request->boolean('is_assigned_to_location');
            if ($isAssigned) {
                $hasInvalid = $assets->contains(function ($u) {
                    $s = strtolower(trim($u->status ?? ''));
                    return $s !== 'tersedia' && $s !== 'di-assign (lt)';
                });
                if ($hasInvalid) {
                    return redirect()->back()->withErrors(['is_assigned_to_location' => 'Semua aset yang dipilih harus berstatus Tersedia untuk di-assign ke lokasi.']);
                }
                $updateData['is_assigned_to_location'] = true;
                $updateData['status'] = 'Di-assign (LT)';
            } else {
                $updateData['is_assigned_to_location'] = false;
            }
        }

        if (!empty($updateData)) {
            foreach ($assets as $asset) {
                $assetData = $updateData;
                if (isset($updateData['is_assigned_to_location']) && !$updateData['is_assigned_to_location']) {
                    if ($asset->status === 'Di-assign (LT)') {
                        $assetData['status'] = 'Tersedia';
                    }
                }
                $asset->update($assetData);
            }
        }

        if ($needApproval) {
            $memoUrl = 'memos/placeholder.pdf';
            if ($request->hasFile('memo_file')) {
                $memoUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file('memo_file'), 'memos');
            }
            $lostDocUrl = null;
            if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
                $lostDocUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file('lost_doc_file'), 'lost_docs');
            }
            $existingPendingAssetIds = AssetDeletionApproval::whereIn('asset_id', $ids)
                ->where('decision', 'pending')
                ->pluck('asset_id')
                ->flip();

            $pendingDmAssets = [];
            foreach ($assets as $asset) {
                if (!$existingPendingAssetIds->has($asset->id)) {
                    $previousStatus = $asset->base_status;
                    $asset->status = "{$previousStatus},Pending:DM";
                    AssetDeletionApproval::create([
                        'asset_id' => $asset->id,
                        'requester_id' => $request->user()->id,
                        'proposed_condition' => $proposedCondition,
                        'previous_condition' => $asset->condition,
                        'previous_status' => $previousStatus,
                        'decision' => 'pending',
                        'admin_note' => $request->input('admin_note'),
                        'dm_note' => null,
                        'approver_id' => null,
                        'requested_at' => now(),
                        'memo_url' => $memoUrl,
                        'lost_doc_url' => $lostDocUrl,
                    ]);

                    $pendingDmAssets[] = $asset;
                }
            }

            if (!empty($pendingDmAssets)) {
                $user = $request->user();
                dispatch(function () use ($pendingDmAssets, $proposedCondition, $user) {
                    $notificationService = app(\App\Services\NotificationService::class);
                    if (count($pendingDmAssets) === 1) {
                        $notificationService->notifyManagerAssetDeletionChange($pendingDmAssets[0]);
                    } else {
                        $notificationService->notifyManagerBulkAssetDeletionChange($pendingDmAssets, $proposedCondition, $user);
                    }
                })->afterResponse();
            }
        }

        $count = count($ids);
        return redirect()->back()->with('success', __('inventory.bulk_unit_updated', ['count' => $count]));
    }
}
