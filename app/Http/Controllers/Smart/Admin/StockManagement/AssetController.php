<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetStatusApproval;
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
            $validated['status'] = 'Pending:BoD/BoC';
        } else {
            $validated['status'] = 'Belum Diverifikasi';
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
            AssetStatusApproval::create([
                'asset_id' => $asset->id,
                'requester_id' => $request->user()->id,
                'proposed_condition' => $proposedCondition,
                'previous_condition' => 'Bagus',
                'previous_status' => 'Tersedia',
                'decision' => 'pending',
                'note' => null,
                'approver_id' => null,
                'requested_at' => now(),
                'memo_url' => $memoUrl,
                'lost_doc_url' => $lostDocUrl,
            ]);
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
        if ($asset->status === 'Pending:BoD/BoC' && $request->hasFile('bod_boc_approval_file')) {
            $request->validate([
                'bod_boc_approval_file' => 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048',
            ], [
                'bod_boc_approval_file.required' => 'Formulir Approval BoD/BoC belum dipilih.',
                'bod_boc_approval_file.mimes' => 'Format Formulir Approval BoD/BoC salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.',
                'bod_boc_approval_file.max' => 'Gagal! Ukuran Formulir Approval BoD/BoC maksimal 2MB.',
            ]);

            DB::transaction(function () use ($asset, $request) {
                $bodBocUrl = app(\App\Services\ImageUploadService::class)->storeImageOrFile($request->file('bod_boc_approval_file'), 'bod_boc_approvals');

                $approval = AssetStatusApproval::where('asset_id', $asset->id)
                    ->where('decision', 'pending')
                    ->first();
                if ($approval) {
                    if ($approval->bod_boc_approval_url && Storage::disk('local')->exists($approval->bod_boc_approval_url)) {
                        Storage::disk('local')->delete($approval->bod_boc_approval_url);
                    }
                    $approval->update(['bod_boc_approval_url' => $bodBocUrl]);
                }

                // Change status to Pending:DM
                $asset->update(['status' => 'Pending:DM']);
                app(\App\Services\NotificationService::class)->notifyIfsManagerPendingDm($asset);

                // Close active lifecycle
                AssetLifecycle::where('asset_id', $asset->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                // Save audit log
                AssetLifecycle::create([
                    'asset_id' => $asset->id,
                    'action_type' => 'Approval',
                    'status' => 'Pending:DM',
                    'condition' => $asset->condition,
                    'location_id' => $asset->location_id,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => $request->user()->id,
                    'note' => 'BoD/BoC menyetujui penghapusan asset',
                ]);
            });

            return redirect()->back()->with('success', __('inventory.bod_boc_uploaded'));
        }

        $arrInactiveConditions = ['Rusak Total', 'Hilang'];
        $arrNeedApproval = ['Rusak Total', 'Hilang'];

        $currentStatusLower = strtolower(trim($asset->status ?? ''));
        $isRestricted = in_array($currentStatusLower, ['tidak aktif', 'pending', 'pending:bod/boc', 'belum diverifikasi', 'verifikasi ditolak']) || str_starts_with($currentStatusLower, 'pending') || in_array($asset->condition, $arrInactiveConditions);

        $borrowingType = $request->input('borrowing_type', $request->input('type'));
        if ($borrowingType !== null) {
            $request->merge(['borrowing_type' => $borrowingType]);
        }

        $rules = [
            'number' => 'required|string|max:50|unique:assets,number,' . $asset->id,
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'status' => ['required', 'string', 'in:Tersedia,Dipinjam,Tidak Aktif,Pending,Pending:BoD/BoC,Belum Diverifikasi,Verifikasi Ditolak'],
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

        $proposedCondition = $request->input('condition');
        $needApproval = in_array($proposedCondition, $arrNeedApproval);

        if ($currentStatusLower === 'dipinjam') {
            if ($request->filled('status') && strtolower(trim($request->input('status'))) !== 'dipinjam') {
                return redirect()->back()->withErrors(['status' => 'Status untuk aset yang sedang Dipinjam hanya dapat diubah melalui Tab Peminjaman.']);
            }
            if (in_array($proposedCondition, $arrInactiveConditions)) {
                return redirect()->back()->withErrors(['condition' => 'Kondisi Rusak Total atau Hilang tidak dapat dipilih saat aset berstatus Dipinjam.']);
            }
            $request->merge(['status' => 'Dipinjam']);
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
                if ($currentStatusLower !== 'tersedia') {
                    return redirect()->back()->withErrors(['status' => 'Status hanya dapat diubah jika aset berstatus Tersedia.']);
                }
            }
            $inputStatusLower = strtolower(trim($request->input('status', '')));
            if ($inputStatusLower === 'tidak aktif' && !in_array($proposedCondition, $arrInactiveConditions)) {
                return redirect()->back()->withErrors(['status' => 'Status Tidak Aktif tidak dapat dipilih secara manual.']);
            }
        }

        if ($needApproval && !$isRestricted) {
            $existing = AssetStatusApproval::where('asset_id', $asset->id)
                ->where('decision', 'pending')
                ->first();
            if (!$existing) {
                $rules['memo_file'] = 'required|file|mimes:pdf,jpeg,jpg,png,webp|max:2048';
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

        $previousStatus = str_starts_with($asset->status ?? '', 'Pending') ? 'Tersedia' : $asset->status;
        $previousCondition = $asset->condition;

        if ($needApproval) {
            $validated['status'] = 'Pending:BoD/BoC';
        }

        $isComp = ($lot->model?->type?->category?->code === 'COMP');
        if ($request->has('comp_specification') || $request->has('specification')) {
            $compSpec = $request->input('comp_specification', $request->input('specification'));
            $validated['comp_specification'] = $isComp ? $compSpec : null;
        }
        unset($validated['specification']);

        $asset->update($validated);

        if ($needApproval) {
            $existing = AssetStatusApproval::where('asset_id', $asset->id)
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
                AssetStatusApproval::create([
                    'asset_id' => $asset->id,
                    'requester_id' => $request->user()->id,
                    'proposed_condition' => $proposedCondition,
                    'previous_condition' => $previousCondition,
                    'previous_status' => $previousStatus,
                    'decision' => 'pending',
                    'note' => null,
                    'approver_id' => null,
                    'requested_at' => now(),
                    'memo_url' => $memoUrl,
                    'lost_doc_url' => $lostDocUrl,
                ]);
            }
        }

        return redirect()->back()->with('success', __('inventory.asset_updated', [], null) ?: __('inventory.unit_updated'));
    }
}
