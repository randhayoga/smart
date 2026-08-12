<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\UnitLifecycle;
use App\Models\Master\Floor;
use App\Models\Master\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class BulkUnitController extends Controller
{
    /**
     * Menyimpan data unit (aset) baru secara massal ke dalam database.
     */
    public function store(Request $request)
    {
        $rules = [
            'number' => 'required|string|max:255',
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'status' => 'required|string|max:255',
            'condition' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'required_without:use_lot_image|nullable|image|max:1024',
            'use_lot_image' => 'nullable',
            'bulk_quantity' => 'required|integer|min:1|max:999',
        ];

        $lot = Lot::with('barang.subcategory.category')->findOrFail($request->input('lot_id'));
        $isVehicle = false;
        if ($lot->barang && $lot->barang->subcategory && $lot->barang->subcategory->category) {
            $catName = strtolower($lot->barang->subcategory->category->name);
            $subcatName = strtolower($lot->barang->subcategory->name);
            $isVehicle = str_contains($catName, 'kendaraan') || str_contains($subcatName, 'kendaraan') ||
                         str_contains($catName, 'mobil') || str_contains($subcatName, 'mobil') ||
                         str_contains($catName, 'motor') || str_contains($subcatName, 'motor');
        }

        if ($isVehicle) {
            $rules['vehicle_registration'] = 'required|string|max:15';
        } else {
            $rules['vehicle_registration'] = 'nullable|string|max:15';
        }

        $arrNeedApproval = ['Rusak Total', 'Hilang'];
        $proposedCondition = $request->input('condition');
        $needApproval = in_array($proposedCondition, $arrNeedApproval);

        $inputStatusLower = strtolower(trim($request->input('status', '')));
        if ($inputStatusLower === 'tidak aktif' && !$needApproval) {
            return redirect()->back()->withErrors(['status' => 'Status Tidak Aktif tidak dapat dipilih secara manual.']);
        }

        if ($needApproval) {
            $rules['memo_file'] = 'required|file|max:2048';
            if ($proposedCondition === 'Hilang') {
                $rules['lost_doc_file'] = 'required|file|max:2048';
            }
        }

        $validated = $request->validate($rules, [
            'bulk_quantity.integer' => 'Tidak boleh desimal.',
        ]);

        if ($needApproval) {
            $validated['status'] = 'Pending:BoD/BoC';
        }

        $quantity = (int)$validated['bulk_quantity'];
        $subcategoryCode = $lot->barang->subcategory->code ?? '';
        $organizerCode = $lot->organizer->name ?? '';

        if ($subcategoryCode && $organizerCode) {
            $combination = "{$subcategoryCode}-{$organizerCode}-PTRE";
            $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');
            
            $count = Unit::where('number', 'like', "%-{$combination}-%")->count();
            $nextSerialVal = $count + 1;
            
            $generatedNumbers = [];
            for ($i = 0; $i < $quantity; $i++) {
                do {
                    $serial = str_pad($nextSerialVal, 5, '0', STR_PAD_LEFT);
                    $num = "{$serial}-{$combination}-{$yy}";
                    $exists = Unit::where('number', $num)->exists() || in_array($num, $generatedNumbers);
                    if ($exists) {
                        $nextSerialVal++;
                    }
                } while ($exists);
                $generatedNumbers[] = $num;
                $nextSerialVal++;
            }
        } else {
            $baseNumber = $validated['number'];
            $suffixPos = strrpos($baseNumber, '-U');
            
            if ($suffixPos !== false) {
                $prefix = substr($baseNumber, 0, $suffixPos + 2);
                $startNumStr = substr($baseNumber, $suffixPos + 2);
                $startNum = (int)$startNumStr;
                $padLength = strlen($startNumStr);
            } else {
                $prefix = $baseNumber . '-';
                $startNum = 1;
                $padLength = 2;
            }

            $generatedNumbers = [];
            for ($i = 0; $i < $quantity; $i++) {
                $num = $prefix . str_pad($startNum + $i, $padLength, '0', STR_PAD_LEFT);
                $generatedNumbers[] = $num;
            }
        }

        $existing = Unit::whereIn('number', $generatedNumbers)->pluck('number')->toArray();
        if (!empty($existing)) {
            return redirect()->back()->withErrors(['number' => 'Beberapa Kode Aset sudah terpakai: ' . implode(', ', $existing)]);
        }

        $finalImagePath = null;
        if ($request->boolean('use_lot_image')) {
            if ($lot->image_url && Storage::disk('local')->exists($lot->image_url)) {
                $finalImagePath = $lot->image_url;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $finalImagePath = $request->file('image_url')->store('inventory', 'local');
        }

        foreach ($generatedNumbers as $num) {
            $unit = Unit::create([
                'number' => $num,
                'lot_id' => $validated['lot_id'],
                'location_id' => $validated['location_id'],
                'floor_id' => $validated['floor_id'] ?? null,
                'room_id' => $validated['room_id'] ?? null,
                'status' => $validated['status'],
                'condition' => $validated['condition'],
                'price' => $validated['price'],
                'image_url' => $finalImagePath,
                'vehicle_registration' => $validated['vehicle_registration'] ?? null,
            ]);

            if ($needApproval) {
                $memoUrl = 'memos/placeholder.pdf';
                if ($request->hasFile('memo_file')) {
                    $memoUrl = $request->file('memo_file')->store('memos', 'local');
                }
                $lostDocUrl = null;
                if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
                    $lostDocUrl = $request->file('lost_doc_file')->store('lost_docs', 'local');
                }
                UnitStatusApproval::create([
                    'unit_id' => $unit->id,
                    'requester_id' => $request->user()->id,
                    'proposed_condition' => $proposedCondition,
                    'previous_condition' => 'Bagus',
                    'previous_status' => $validated['status'] ?? 'Tersedia',
                    'decision' => 'pending',
                    'note' => null,
                    'approver_id' => null,
                    'requested_at' => now(),
                    'memo_url' => $memoUrl,
                    'lost_doc_url' => $lostDocUrl,
                ]);
            }
        }

        return redirect()->back()->with('success', "Berhasil membuat {$quantity} aset secara otomatis.");
    }

    /**
     * Memperbarui beberapa unit (aset) sekaligus (bulk update).
     */
    public function update(Request $request)
    {
        $messages = [
            'ids.required' => 'Aset belum dipilih.',
            'status.in' => 'Status yang dipilih tidak valid.',
            'condition.in' => 'Kondisi yang dipilih tidak valid.',
            'location_id.exists' => 'Lokasi tidak ditemukan.',
            'floor_id.exists' => 'Lantai tidak ditemukan.',
            'room_id.exists' => 'Ruangan tidak ditemukan.',
            'price.numeric' => 'Harga Satuan harus berupa angka.',
            'price.min' => 'Harga Satuan minimal 0.',
            'price.max' => 'Harga Satuan terlalu besar.',
            'image_url.image' => 'Format foto salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png.',
            'image_url.mimes' => 'Format foto salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png.',
            'image_url.max' => 'Gagal! Ukuran foto maksimal 1MB.',
        ];

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:units,id',
            'status' => ['nullable', 'string', 'in:Tersedia,Dipinjam,Standby,Tidak Aktif,Pending,Pending:BoD/BoC'],
            'condition' => ['nullable', 'string', 'in:Bagus,Rusak,QC Passed,Lelang/Hibah,Rusak Total,Hilang'],
            'location_id' => 'nullable|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'use_lot_image' => 'nullable',
            'image_url' => 'nullable|image|mimes:jpeg,jpg,png|max:1024',
        ], $messages);

        $ids = $validated['ids'];
        $units = Unit::whereIn('id', $ids)->get();
        if ($units->isEmpty()) {
            return redirect()->back()->withErrors(['ids' => 'Tidak ada unit yang ditemukan.']);
        }

        $allPendingBodBoc = $units->every(fn($u) => $u->status === 'Pending:BoD/BoC');
        if ($allPendingBodBoc && $request->hasFile('bod_boc_approval_file')) {
            $request->validate([
                'bod_boc_approval_file' => 'required|file|mimes:pdf,jpeg,jpg,png|max:2048',
            ], [
                'bod_boc_approval_file.required' => 'Formulir Approval BoD/BoC belum dipilih.',
                'bod_boc_approval_file.mimes' => 'Format Formulir Approval BoD/BoC salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png.',
                'bod_boc_approval_file.max' => 'Gagal! Ukuran Formulir Approval BoD/BoC maksimal 2MB.',
            ]);

            DB::transaction(function () use ($units, $request) {
                $bodBocUrl = $request->file('bod_boc_approval_file')->store('bod_boc_approvals', 'local');

                foreach ($units as $unit) {
                    $approval = UnitStatusApproval::where('unit_id', $unit->id)
                        ->where('decision', 'pending')
                        ->first();
                    if ($approval) {
                        if ($approval->bod_boc_approval_url && Storage::disk('local')->exists($approval->bod_boc_approval_url)) {
                            Storage::disk('local')->delete($approval->bod_boc_approval_url);
                        }
                        $approval->update(['bod_boc_approval_url' => $bodBocUrl]);
                    }

                    $unit->update(['status' => 'Pending:DM']);
                    app(\App\Services\NotificationService::class)->notifyIfsManagerPendingDm($unit);

                    UnitLifecycle::where('unit_id', $unit->id)
                        ->whereNull('end_date')
                        ->update(['end_date' => now()]);

                    UnitLifecycle::create([
                        'unit_id' => $unit->id,
                        'action_type' => 'Approval',
                        'status' => 'Pending:DM',
                        'condition' => $unit->condition,
                        'location_id' => $unit->location_id,
                        'floor_id' => $unit->floor_id,
                        'room_id' => $unit->room_id,
                        'start_date' => now(),
                        'end_date' => null,
                        'actor_id' => $request->user()->id,
                        'note' => 'BoD/BoC menyetujui penghapusan asset',
                    ]);
                }
            });

            return redirect()->back()->with('success', count($units) . ' aset terpilih berhasil disetujui BoD/BoC dan status diubah menjadi Pending:DM.');
        }

        if ($request->filled('floor_id') && $request->filled('location_id')) {
            $floor = Floor::find($request->input('floor_id'));
            if (!$floor || (int)$floor->location_id !== (int)$request->input('location_id')) {
                return redirect()->back()->withErrors(['floor_id' => 'Lantai tidak sesuai dengan lokasi yang dipilih.']);
            }
        }

        if ($request->filled('room_id') && $request->filled('floor_id')) {
            $room = Room::find($request->input('room_id'));
            if (!$room || (int)$room->floor_id !== (int)$request->input('floor_id')) {
                return redirect()->back()->withErrors(['room_id' => 'Ruangan tidak sesuai dengan lantai yang dipilih.']);
            }
        }

        $arrInactiveConditions = ['Rusak Total', 'Hilang', 'Lelang/Hibah'];
        $hasRestrictedUnits = $units->contains(function ($u) use ($arrInactiveConditions) {
            $s = strtolower(trim($u->status ?? ''));
            return in_array($s, ['tidak aktif', 'pending', 'pending:bod/boc']) || str_starts_with($s, 'pending') || in_array($u->condition, $arrInactiveConditions);
        });

        if ($hasRestrictedUnits) {
            if ($request->filled('status') || $request->filled('condition')) {
                return redirect()->back()->withErrors(['status' => 'Terdapat aset dengan status Tidak Aktif atau Pending. Status dan kondisi aset tersebut tidak dapat diubah.']);
            }
        } else {
            $inputStatusLower = strtolower(trim($request->input('status', '')));
            $proposedCondition = $request->input('condition');
            if ($inputStatusLower === 'tidak aktif' && !in_array($proposedCondition, $arrInactiveConditions)) {
                return redirect()->back()->withErrors(['status' => 'Status Tidak Aktif tidak dapat dipilih secara manual.']);
            }
        }

        $arrNeedApproval = ['Rusak Total', 'Hilang'];
        $proposedCondition = $request->input('condition');
        $needApproval = $request->filled('condition') && in_array($proposedCondition, $arrNeedApproval);

        if ($needApproval && !$hasRestrictedUnits) {
            $rulesForApproval = [
                'memo_file' => 'required|file|mimes:pdf,jpeg,jpg,png|max:2048',
            ];
            $messagesForApproval = [
                'memo_file.required' => 'Berita Acara / Memo wajib diisi jika kondisi Hilang atau Rusak Total.',
                'memo_file.mimes' => 'Format Berita Acara / Memo salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png.',
                'memo_file.max' => 'Gagal! Ukuran Berita Acara / Memo maksimal 2MB.',
            ];
            if ($proposedCondition === 'Hilang') {
                $rulesForApproval['lost_doc_file'] = 'required|file|mimes:pdf,jpeg,jpg,png|max:2048';
                $messagesForApproval['lost_doc_file.required'] = 'Surat Keterangan Kehilangan wajib diisi jika kondisi Hilang.';
                $messagesForApproval['lost_doc_file.mimes'] = 'Format Surat Keterangan Kehilangan salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png.';
                $messagesForApproval['lost_doc_file.max'] = 'Gagal! Ukuran Surat Keterangan Kehilangan maksimal 2MB.';
            }
            $request->validate($rulesForApproval, $messagesForApproval);
        }

        $updateData = [];

        // 1. Status & Condition
        if ($needApproval) {
            $updateData['status'] = 'Pending:BoD/BoC';
        } else if ($request->filled('status')) {
            $updateData['status'] = $request->input('status');
        }

        if ($request->filled('condition')) {
            $updateData['condition'] = $request->input('condition');
        }

        // 2. Location, Floor, Room
        if ($request->filled('location_id')) {
            $updateData['location_id'] = $request->input('location_id');
            $updateData['floor_id'] = $request->input('floor_id');
            $updateData['room_id'] = $request->input('room_id');
        } else {
            if ($request->has('floor_id')) {
                $updateData['floor_id'] = $request->input('floor_id');
            }
            if ($request->has('room_id')) {
                $updateData['room_id'] = $request->input('room_id');
            }
        }

        // 3. Price
        if ($request->has('price')) {
            $updateData['price'] = $request->input('price') !== null ? (float)$request->input('price') : null;
        }

        // 4. Image URL / Use LOT Image
        $finalImagePath = null;
        $hasNewImage = false;

        if ($request->boolean('use_lot_image')) {
            $lot = Lot::findOrFail($units->first()->lot_id);
            if ($lot->image_url && Storage::disk('local')->exists($lot->image_url)) {
                $finalImagePath = $lot->image_url;
                $hasNewImage = true;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $finalImagePath = $request->file('image_url')->store('inventory', 'local');
            $hasNewImage = true;
        }

        if ($hasNewImage) {
            $updateData['image_url'] = $finalImagePath;

            // Delete old images for each unit if they are not shared
            foreach ($units as $unit) {
                if ($unit->image_url && Storage::disk('local')->exists($unit->image_url)) {
                    $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                        || Lot::where('image_url', $unit->image_url)->exists()
                        || Barang::where('image_url', $unit->image_url)->exists();
                    if (!$isShared) {
                        Storage::disk('local')->delete($unit->image_url);
                    }
                }
            }
        }

        if (!empty($updateData)) {
            foreach ($units as $unit) {
                $unit->update($updateData);
            }
        }

        // 5. Handle approvals if condition is changed to a condition requiring approval
        if ($needApproval) {
            $memoUrl = 'memos/placeholder.pdf';
            if ($request->hasFile('memo_file')) {
                $memoUrl = $request->file('memo_file')->store('memos', 'local');
            }
            $lostDocUrl = null;
            if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
                $lostDocUrl = $request->file('lost_doc_file')->store('lost_docs', 'local');
            }
            foreach ($units as $unit) {
                $existing = UnitStatusApproval::where('unit_id', $unit->id)
                    ->where('decision', 'pending')
                    ->first();
                if (!$existing) {
                    UnitStatusApproval::create([
                        'unit_id' => $unit->id,
                        'requester_id' => $request->user()->id,
                        'proposed_condition' => $proposedCondition,
                        'previous_condition' => $unit->condition,
                        'previous_status' => str_starts_with($unit->status ?? '', 'Pending') ? 'Tersedia' : $unit->status,
                        'decision' => 'pending',
                        'note' => null,
                        'approver_id' => null,
                        'requested_at' => now(),
                        'memo_url' => $memoUrl,
                        'lost_doc_url' => $lostDocUrl,
                    ]);
                }
            }
        }

        $count = count($ids);
        return redirect()->back()->with('success', $count . ' aset terpilih berhasil diperbarui.');
    }
}
