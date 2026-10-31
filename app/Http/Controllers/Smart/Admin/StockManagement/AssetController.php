<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Request\RequestFulfillment;
use App\Models\TbProject;
use App\Services\Inventory\AssetNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Asset Controller managing individual physical asset units, status lifecycles, condition transitions, and disposal requests.
 */
class AssetController extends Controller
{

    /**
     * Store a newly created asset unit in storage.
     */
    public function store(Request $request)
    {
        $lot = Lot::with('model.type.category', 'organizer')->findOrFail($request->input('lot_id'));
        $generatedNumber = app(AssetNumberService::class)->generateAssetNumber($lot);
        $request->merge(['number' => $generatedNumber]);

        $borrowingType = $request->input('borrowing_type', $request->input('type'));
        if ($borrowingType !== null) {
            $request->merge(['borrowing_type' => $borrowingType]);
        }

        $rules = [
            'number' => 'required|string|max:50|unique:assets,number',
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
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => 'required_if:burden,Project|nullable|integer',
        ];
 
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

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            if ($validator->errors()->has('borrowing_type')) {
                $validator->errors()->add('type', $validator->errors()->first('borrowing_type'));
            }
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        $validated = $validator->validated();

        $isComp = ($lot->model?->type?->category?->code === 'COMP');
        $compSpec = $request->input('comp_specification', $request->input('specification'));
        $validated['comp_specification'] = $isComp ? $compSpec : null;
        unset($validated['specification']);

        if ($needApproval) {
            $validated['status'] = 'Tersedia';
            $validated['approval_status'] = 'Pending:DM';
        } else {
            $validated['status'] = 'Belum Diverifikasi';
            $validated['approval_status'] = null;
        }

        $imageUploadService = app(\App\Services\ImageUploadService::class);
 
        if ($request->boolean('use_lot_image')) {
            if ($lot->image_url && Storage::disk('local')->exists($lot->image_url)) {
                $validated['image_url'] = $lot->image_url;
            } else {
                $validated['image_url'] = null;
            }
        } else if ($request->hasFile('image_url')) {
            $imagePath = $imageUploadService->storeAsWebp($request->file('image_url'), 'inventory');
            $validated['image_url'] = $imagePath;
        } else {
            $validated['image_url'] = null;
        }
 
        unset($validated['use_lot_image']);
        unset($validated['type']);
        $validated['burden'] = $validated['burden'] ?? 'Corporate';
        $validated['project_id'] = $validated['burden'] === 'Project' ? ($validated['project_id'] ?? null) : null;

        $asset = Asset::create($validated);
 
        if ($needApproval) {
            $memoUrl = 'memos/placeholder.pdf';
            if ($request->hasFile('memo_file')) {
                $memoUrl = $imageUploadService->storeImageOrFile($request->file('memo_file'), 'memos');
            }
            $lostDocUrl = null;
            if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
                $lostDocUrl = $imageUploadService->storeImageOrFile($request->file('lost_doc_file'), 'lost_docs');
            }
            $approval = AssetDeletionApproval::create([
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

            dispatch(function () use ($asset, $approval) {
                app(\App\Services\NotificationService::class)->notifyManagerAssetDeletionChange($asset, $approval);
            })->afterResponse();
        } elseif ($asset->status === 'Belum Diverifikasi') {
            $approval = AssetActivationApproval::create([
                'asset_id' => $asset->id,
                'requester_id' => $request->user()->id,
                'decision' => 'pending',
                'requested_at' => now(),
            ]);

            app(\App\Services\NotificationService::class)->notifyManagerNewAssetActivation($asset, $approval);
        }
 
        return redirect()->back()->with('success', __('inventory.asset_created', [], null) ?: __('inventory.unit_created'));
    }
 
    /**
     * Update the specified asset unit in storage.
     */
    public function update(Request $request, Asset $asset)
    {
        if ($asset->hasStatus('Pending:BoD/BoC') && ($request->hasFile('bod_boc_approval_file') || $request->hasFile('bod_boc_disapproval_file'))) {
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

            DB::transaction(function () use ($asset, $request, $isDisapproval, $fieldName) {
                $approval = AssetDeletionApproval::where('asset_id', $asset->id)
                    ->where('decision', 'approved')
                    ->latest('id')
                    ->first();

                $folder = $isDisapproval ? 'bod_boc_disapprovals' : 'bod_boc_approvals';
                $bodBocUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file($fieldName), $folder);

                if ($isDisapproval) {
                    $pastDisapprovals = AssetDeletionApproval::where('asset_id', $asset->id)
                        ->whereNotNull('bod_boc_disapproval_url')
                        ->get();
                    foreach ($pastDisapprovals as $past) {
                        if ($past->bod_boc_disapproval_url && Storage::disk('local')->exists($past->bod_boc_disapproval_url)) {
                            Storage::disk('local')->delete($past->bod_boc_disapproval_url);
                        }
                        if ($approval && $past->id !== $approval->id) {
                            $past->update(['bod_boc_disapproval_url' => null]);
                        }
                    }
                } else {
                    $pastApprovals = AssetDeletionApproval::where('asset_id', $asset->id)
                        ->whereNotNull('bod_boc_approval_url')
                        ->get();
                    foreach ($pastApprovals as $past) {
                        if ($past->bod_boc_approval_url && Storage::disk('local')->exists($past->bod_boc_approval_url)) {
                            Storage::disk('local')->delete($past->bod_boc_approval_url);
                        }
                        if ($approval && $past->id !== $approval->id) {
                            $past->update(['bod_boc_approval_url' => null]);
                        }
                    }
                }

                if ($approval) {
                    if ($isDisapproval) {
                        $approval->update([
                            'bod_boc_disapproval_url' => $bodBocUrl,
                            'decision' => 'rejected',
                        ]);
                    } else {
                        $approval->update(['bod_boc_approval_url' => $bodBocUrl]);
                    }
                }

                $proposedCondition = $approval?->proposed_condition ?? $asset->condition;

                if ($isDisapproval) {
                    $targetStatus = $approval?->previous_status ?? 'Tersedia';
                    $targetCondition = $approval?->previous_condition ?? 'Bagus';

                    Asset::withoutEvents(fn () => $asset->update([
                        'status' => $targetStatus,
                        'condition' => $targetCondition,
                        'approval_status' => null,
                    ]));

                    AssetLifecycle::where('asset_id', $asset->id)
                        ->whereNull('end_date')
                        ->update(['end_date' => now()]);

                    AssetLifecycle::create([
                        'asset_id' => $asset->id,
                        'action_type' => 'Penolakan',
                        'status' => $targetStatus,
                        'condition' => $targetCondition,
                        'location_id' => $asset->location_id,
                        'start_date' => now(),
                        'end_date' => null,
                        'actor_id' => $request->user()->id,
                        'note' => "BoD/BoC menolak penghapusan asset dengan kondisi {$proposedCondition}",
                    ]);
                } else {
                    Asset::withoutEvents(fn () => $asset->update([
                        'status' => 'Dihapus',
                        'approval_status' => null,
                    ]));

                    AssetLifecycle::where('asset_id', $asset->id)
                        ->whereNull('end_date')
                        ->update(['end_date' => now()]);

                    AssetLifecycle::create([
                        'asset_id' => $asset->id,
                        'action_type' => 'Persetujuan',
                        'status' => 'Dihapus',
                        'condition' => $asset->condition,
                        'location_id' => $asset->location_id,
                        'start_date' => now(),
                        'end_date' => null,
                        'actor_id' => $request->user()->id,
                        'note' => "BoD/BoC menyetujui penghapusan asset dengan kondisi {$proposedCondition}",
                    ]);
                }
            });

            $successMsg = $isDisapproval
                ? __('inventory.bod_boc_disapproved')
                : __('inventory.bod_boc_uploaded');

            return redirect()->back()->with('success', $successMsg);
        }

        $arrInactiveConditions = ['Rusak Total', 'Hilang'];
        $arrNeedApproval = ['Rusak Total', 'Hilang'];

        $currentStatusLower = strtolower(trim($asset->status ?? ''));
        $isRestricted = collect($asset->statuses)->contains(fn ($s) => in_array(strtolower($s), ['tidak aktif', 'dihapus', 'pending', 'pending:dm', 'pending:bod/boc', 'belum diverifikasi', 'verifikasi ditolak']) || str_starts_with(strtolower($s), 'pending')) || in_array($asset->condition, $arrInactiveConditions);

        $borrowingType = $request->input('borrowing_type', $request->input('type'));
        if ($borrowingType !== null) {
            $request->merge(['borrowing_type' => $borrowingType]);
        }

        $rules = [
            'number' => 'required|string|max:50|unique:assets,number,' . $asset->id,
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'status' => [
                'required',
                'string',
                Rule::in([
                    'Tersedia',
                    'Dipinjam (ST)',
                    'Dipinjam (sewa)',
                    'Tidak Aktif',
                    'Dihapus',
                    'Pending',
                    'Pending:DM',
                    'Pending:BoD/BoC',
                    'Belum Diverifikasi',
                    'Verifikasi Ditolak',
                    'Di-assign (LT)',
                ]),
            ],
            'condition' => ['required', 'string', 'in:Bagus,Rusak,Rusak Total,Hilang'],
            'borrowing_type' => ['required', 'string', 'in:LT,ST'],
            'classification' => ['required', 'string', 'in:Aset,Inventaris'],
            'comp_specification' => 'nullable|string|max:255',
            'specification' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024',
            'delete_image' => 'nullable|boolean',
            'use_lot_image' => 'nullable',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => 'required_if:burden,Project|nullable|integer',
            'is_assigned_to_location' => 'nullable|boolean',
        ];

        if ($request->has('is_assigned_to_location') && $request->input('is_assigned_to_location') !== null) {
            $isAssigned = $request->boolean('is_assigned_to_location');
            if ($isAssigned) {
                if (!in_array($currentStatusLower, ['tersedia', 'di-assign (lt)'], true)) {
                    return redirect()->back()->withErrors(['is_assigned_to_location' => 'Aset hanya dapat di-assign ke lokasi jika berstatus Tersedia.']);
                }
                $request->merge(['status' => 'Di-assign (LT)']);
            } else {
                if ($currentStatusLower === 'di-assign (lt)') {
                    $request->merge(['status' => 'Tersedia']);
                }
            }
        }

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

        $proposedCondition = $request->input('condition');
        $needApproval = in_array($proposedCondition, $arrNeedApproval);

        if (in_array($currentStatusLower, ['dipinjam (st)', 'dipinjam (sewa)'], true)) {
            $statusLabel = $currentStatusLower === 'dipinjam (sewa)' ? 'Dipinjam (sewa)' : 'Dipinjam (ST)';
            if ($request->filled('status') && strtolower(trim($request->input('status'))) !== $currentStatusLower) {
                return redirect()->back()->withErrors(['status' => "Status untuk aset yang sedang {$statusLabel} hanya dapat diubah melalui peminjaman."]);
            }
            if (in_array($proposedCondition, $arrInactiveConditions)) {
                return redirect()->back()->withErrors(['condition' => "Kondisi Rusak Total atau Hilang tidak dapat dipilih saat aset berstatus {$statusLabel}."]);
            }
            $request->merge(['status' => $statusLabel]);
        } else if ($isRestricted) {
            if ($request->filled('status') && strtolower(trim($request->input('status'))) !== $currentStatusLower) {
                return redirect()->back()->withErrors(['status' => 'Status untuk aset yang Tidak Aktif atau Pending tidak dapat diubah.']);
            }
            if ($request->filled('condition') && $request->input('condition') !== $asset->condition) {
                return redirect()->back()->withErrors(['condition' => 'Kondisi untuk aset yang Tidak Aktif atau Pending tidak dapat diubah.']);
            }
            $request->merge([
                'status' => $asset->status,
                'condition' => $asset->condition,
            ]);
        } else {
            if ($request->filled('status') && strtolower(trim($request->input('status'))) !== $currentStatusLower) {
                if ($currentStatusLower !== 'tersedia' && $currentStatusLower !== 'di-assign (lt)') {
                    return redirect()->back()->withErrors(['status' => 'Status hanya dapat diubah jika aset berstatus Tersedia.']);
                }
            }
            $inputStatusLower = strtolower(trim($request->input('status', '')));
            if ($inputStatusLower === 'tidak aktif' && !in_array($proposedCondition, $arrInactiveConditions)) {
                return redirect()->back()->withErrors(['status' => 'Status Tidak Aktif tidak dapat dipilih secara manual.']);
            }
        }

        if ($needApproval && !$isRestricted) {
            $existing = AssetDeletionApproval::where('asset_id', $asset->id)
                ->where('decision', 'pending')
                ->first();
            if (!$existing) {
                $rules['memo_file'] = 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048';
                $rules['admin_note'] = 'nullable|string';
                if ($proposedCondition === 'Hilang') {
                    $rules['lost_doc_file'] = 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048';
                }
            }
        }

        $messages = [
            'number.required' => 'Nomor Aset wajib diisi.',
            'number.unique' => 'Nomor Aset sudah terpakai.',
            'lot_id.required' => 'LOT belum dipilih.',
            'lot_id.exists' => 'LOT tidak ditemukan.',
            'location_id.required' => 'Lokasi belum dipilih.',
            'location_id.exists' => 'Lokasi tidak ditemukan.',
            'status.required' => 'Status belum dipilih.',
            'status.in' => 'Status yang dipilih tidak valid.',
            'condition.required' => 'Kondisi belum dipilih.',
            'condition.in' => 'Kondisi yang dipilih tidak valid.',
            'borrowing_type.in' => 'Tipe Peminjaman yang dipilih tidak valid.',
            'classification.in' => 'Klasifikasi yang dipilih tidak valid.',
            'price.numeric' => 'Harga Satuan harus berupa angka.',
            'price.min' => 'Harga Satuan minimal 0.',
            'price.max' => 'Harga Satuan terlalu besar.',
            'image_url.image' => 'Format foto salah! Hanya diperbolehkan file .jpg, .jpeg, .png, atau .webp.',
            'image_url.mimes' => 'Format foto salah! Hanya diperbolehkan file .jpg, .jpeg, .png, atau .webp.',
            'image_url.max' => 'Gagal! Ukuran foto maksimal 1MB.',
            'vehicle_registration.required' => 'TNKB (Nomor Polisi) belum diisi.',
            'vehicle_registration.max' => 'TNKB (Nomor Polisi) maksimal 15 karakter.',
            'memo_file.required' => 'Berita Acara / Memo belum dipilih.',
            'memo_file.mimes' => 'Format Berita Acara / Memo salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.',
            'memo_file.max' => 'Gagal! Ukuran Berita Acara / Memo maksimal 2MB.',
            'lost_doc_file.required' => 'Surat Keterangan Kehilangan belum dipilih.',
            'lost_doc_file.mimes' => 'Format Surat Keterangan Kehilangan salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.',
            'lost_doc_file.max' => 'Gagal! Ukuran Surat Keterangan Kehilangan maksimal 2MB.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            if ($validator->errors()->has('borrowing_type')) {
                $validator->errors()->add('type', $validator->errors()->first('borrowing_type'));
            }
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        $validated = $validator->validated();

        if ($request->boolean('delete_image')) {
            if ($asset->image_url && Storage::disk('local')->exists($asset->image_url)) {
                $isShared = Asset::where('image_url', $asset->image_url)->where('id', '!=', $asset->id)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($asset->image_url);
                }
            }
            $validated['image_url'] = null;
        } else if ($request->boolean('use_lot_image')) {
            if ($asset->image_url && Storage::disk('local')->exists($asset->image_url)) {
                $isShared = Asset::where('image_url', $asset->image_url)->where('id', '!=', $asset->id)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($asset->image_url);
                }
            }
            $validated['image_url'] = null;
        } else if ($request->hasFile('image_url')) {
            if ($asset->image_url && Storage::disk('local')->exists($asset->image_url)) {
                $isShared = Asset::where('image_url', $asset->image_url)->where('id', '!=', $asset->id)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($asset->image_url);
                }
            }
            $imagePath = app(\App\Services\ImageUploadService::class)->storeAsWebp($request->file('image_url'), 'inventory');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        unset($validated['use_lot_image']);
        unset($validated['delete_image']);
        unset($validated['type']);
        if (!empty($validated['burden'])) {
            $validated['project_id'] = $validated['burden'] === 'Project' ? ($validated['project_id'] ?? null) : null;
        } else {
            unset($validated['burden'], $validated['project_id']);
        }

        $previousStatus = $asset->base_status;
        $previousCondition = $asset->condition;

        if ($needApproval) {
            $validated['status'] = $previousStatus;
            $validated['approval_status'] = 'Pending:DM';
        }

        $isComp = ($lot->model?->type?->category?->code === 'COMP');
        if ($request->has('comp_specification') || $request->has('specification')) {
            $compSpec = $request->input('comp_specification', $request->input('specification'));
            $validated['comp_specification'] = $isComp ? $compSpec : null;
        }
        unset($validated['specification']);

        $asset->update($validated);

        if ($needApproval) {
            $existing = AssetDeletionApproval::where('asset_id', $asset->id)
                ->where('decision', 'pending')
                ->first();
            if (!$existing) {
                $memoUrl = 'memos/placeholder.pdf';
                if ($request->hasFile('memo_file')) {
                    $memoUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file('memo_file'), 'memos');
                }
                $lostDocUrl = null;
                if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
                    $lostDocUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file('lost_doc_file'), 'lost_docs');
                }
                $approval = AssetDeletionApproval::create([
                    'asset_id' => $asset->id,
                    'requester_id' => $request->user()->id,
                    'proposed_condition' => $proposedCondition,
                    'previous_condition' => $previousCondition,
                    'previous_status' => $previousStatus,
                    'decision' => 'pending',
                    'admin_note' => $request->input('admin_note'),
                    'dm_note' => null,
                    'approver_id' => null,
                    'requested_at' => now(),
                    'memo_url' => $memoUrl,
                    'lost_doc_url' => $lostDocUrl,
                ]);

                dispatch(function () use ($asset, $approval) {
                    app(\App\Services\NotificationService::class)->notifyManagerAssetDeletionChange($asset, $approval);
                })->afterResponse();
            }
        }

        return redirect()->back()->with('success', __('inventory.asset_updated', [], null) ?: __('inventory.unit_updated'));
    }

    /**
     * Display the specified asset as JSON for modal inspection.
     */
    public function show(Asset $asset): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->formatAssetDetail($asset));
    }

    /**
     * Look up and display asset by its unique number or code.
     */
    public function showByCode(string $code): \Illuminate\Http\JsonResponse
    {
        $asset = (new Asset())->resolveRouteBinding($code);

        if (!$asset) {
            abort(404, 'Asset not found.');
        }

        return response()->json($this->formatAssetDetail($asset));
    }

    /**
     * Map complete asset relations into formatted DetailAssetModal payload.
     *
     * @param Asset $asset
     * @return array<string, mixed>
     */
    protected function formatAssetDetail(Asset $asset): array
    {
        $asset->loadMissing([
            'location.parent.parent',
            'deletionApprovals',
            'lot.model.type.category',
            'lot.model.brand',
            'lot.model.uom',
            'lot.organizer',
            'lot.vendor',
            'lot.legacyVendor',
            'legacyVendor',
            'lifecycles.actor',
        ]);

        $pendingApproval = $asset->deletionApprovals->firstWhere('decision', 'pending');
        $approvedApproval = ($asset->hasStatus('Tidak Aktif') || $asset->hasStatus('Pending:BoD/BoC') || $asset->hasStatus('Dihapus'))
            ? $asset->deletionApprovals->where('decision', 'approved')->sortByDesc('id')->first()
            : null;
        $rejectedApproval = (!$pendingApproval && !$approvedApproval)
            ? $asset->deletionApprovals->where('decision', 'rejected')->sortByDesc('id')->first()
            : null;
        $latestDisapproval = $asset->deletionApprovals->whereNotNull('bod_boc_disapproval_url')->sortByDesc('id')->first();

        $lot = $asset->lot;
        $model = $lot?->model;
        $brand = $model?->brand?->name ?? '-';
        $location = $asset->location ? $asset->location->full_name : '-';

        return [
            'id' => $asset->id,
            'number' => $asset->number,
            'legacy_number' => $asset->legacy_number,
            'status' => $asset->status,
            'proposed_status' => $pendingApproval 
                ? $pendingApproval->proposed_condition 
                : ($approvedApproval ? $approvedApproval->proposed_condition : null),
            'proposed_condition' => $pendingApproval 
                ? $pendingApproval->proposed_condition 
                : ($approvedApproval ? $approvedApproval->proposed_condition : null),
            'memo_url' => $pendingApproval 
                ? $pendingApproval->memo_url 
                : ($approvedApproval ? $approvedApproval->memo_url : null),
            'lost_doc_url' => $pendingApproval 
                ? $pendingApproval->lost_doc_url 
                : ($approvedApproval ? $approvedApproval->lost_doc_url : null),
            'bod_boc_approval_url' => $pendingApproval 
                ? $pendingApproval->bod_boc_approval_url 
                : ($approvedApproval ? $approvedApproval->bod_boc_approval_url : null),
            'bod_boc_disapproval_url' => $latestDisapproval?->bod_boc_disapproval_url,
            'admin_note' => $pendingApproval ? $pendingApproval->admin_note : ($approvedApproval ? $approvedApproval->admin_note : ($rejectedApproval ? $rejectedApproval->admin_note : null)),
            'dm_note' => $pendingApproval ? $pendingApproval->dm_note : ($approvedApproval ? $approvedApproval->dm_note : ($rejectedApproval ? $rejectedApproval->dm_note : null)),
            'condition' => $asset->condition,
            'price' => $asset->price,
            'type' => $asset->borrowing_type,
            'borrowing_type' => $asset->borrowing_type,
            'classification' => $asset->classification,
            'comp_specification' => $asset->comp_specification,
            'specification' => $asset->comp_specification ?: ($asset->specification ?: ($model?->specification ?? '-')),
            'image_url' => $asset->image_url,
            'vehicle_registration' => $asset->vehicle_registration,
            'burden' => $asset->burden,
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at ? $asset->updated_at->format('d-m-Y H:i') : '-',
            
            // Location
            'location' => $location,
            'location_id' => $asset->location_id,

            // Lot
            'lot_id' => $lot?->id,
            'lot_number' => $lot?->number ?? '-',
            'lot_imageUrl' => $lot?->image_url,
            'lot_unitPrice' => $lot?->unit_price,
            'organizer' => $lot?->organizer?->name ?? '-',
            'organizer_id' => $lot?->organizer_id,
            'vendor' => $lot?->vendor_name ?? '-',
            'vendor_id' => $lot?->vendor_id,
            'lot_organizer' => $lot?->organizer?->name ?? '-',
            'lot_vendor' => $lot?->vendor_name ?? '-',
            'lot_po_number' => $lot?->po_number ?? '-',
            'lot_purchase_grant_date' => $lot?->purchase_grant_date ? $lot->purchase_grant_date->format('Y-m-d') : null,
            'lot_age' => $lot?->age,

            // Model
            'model_id' => $model?->id,
            'model_number' => $model?->number ?? '-',
            'model_code' => $model?->number ?? '-',
            'model_name' => $model?->name ?? '-',
            'model_brand' => $brand,
            'model_specification' => $model?->specification ?? '-',
            'model_category' => $model?->type?->category?->name ?? '-',
            'model_category_code' => $model?->type?->category?->code ?? null,
            'model_type' => $model?->type?->name ?? '-',
            'type_name' => $model?->type?->name ?? '-',
            'model_uom' => $model?->uom?->name ?? '-',

            // Compatibility aliases for DetailAssetModal
            'barang_id' => $model?->id,
            'barang_code' => $model?->number ?? '-',
            'barang_nama' => $model?->name ?? '-',
            'barang_brand' => $brand,
            'barang_specification' => $model?->specification ?? '-',
            'barang_category' => $model?->type?->category?->name ?? '-',
            'barang_subcategory' => $model?->type?->name ?? '-',
            'barang_uom' => $model?->uom?->name ?? '-',

            // Lifecycle history for AuditTrailTab
            'lifecycles' => $asset->lifecycles->map(function ($log) {
                return [
                    'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                    'status' => $log->status,
                    'action_type' => $log->action_type,
                    'aktor' => ($log->action_type === 'Approval' && str_contains($log->note ?? '', 'BoD/BoC')) ? 'BoD/BoC' : ($log->actor->name ?? '-'),
                    'kondisi_awal' => $log->initial_condition ?? '-',
                    'kondisi_terakhir' => $log->final_condition ?? '-',
                    'status_awal' => $log->initial_status ?? '-',
                    'status_terakhir' => $log->final_status ?? '-',
                    'alasan' => $log->note ?? '-',
                    'durasi' => $log->formatted_duration,
                ];
            })->values()->toArray(),
        ];
    }
}
