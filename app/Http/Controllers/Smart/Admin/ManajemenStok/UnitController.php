<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Vendor;
use App\Models\Request\RequestUnitAssignment;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    /**
     * Menampilkan halaman daftar aset (Daftar Aset).
     */
    public function index(Request $request): Response
    {
        $units = Unit::with([
            'location', 'floor', 'room', 'statusApprovals',
            'lot.barang.subcategory.category', 'lot.barang.brand',
            'lot.organizer', 'lot.vendor', 'lifecycles.actor'
        ])
        ->get()
        ->map(function ($unit) {
            $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
            $approvedApproval = $unit->status === 'Dihapus' 
                ? $unit->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;
            $barang = $unit->lot->barang ?? null;
            return [
                'id' => $unit->id,
                'number' => $unit->number, // Kode Aset
                'status' => $unit->status,
                'proposed_status' => $pendingApproval 
                    ? $pendingApproval->proposed_status 
                    : ($approvedApproval ? $approvedApproval->proposed_status : null),
                'memo_url' => $pendingApproval 
                    ? $pendingApproval->memo_url 
                    : ($approvedApproval ? $approvedApproval->memo_url : null),
                'lost_doc_url' => $pendingApproval 
                    ? $pendingApproval->lost_doc_url 
                    : ($approvedApproval ? $approvedApproval->lost_doc_url : null),
                'condition' => $unit->condition,
                'price' => $unit->price,
                'image_url' => $unit->image_url,
                'vehicle_registration' => $unit->vehicle_registration,
                'updated_at' => $unit->updated_at ? $unit->updated_at->format('d/m/Y H:i') : '-',
                
                // Location info
                'location' => $unit->location->name ?? '-',
                'location_id' => $unit->location_id,
                'floor' => $unit->floor->name ?? null,
                'floor_id' => $unit->floor_id,
                'room' => $unit->room->name ?? null,
                'room_id' => $unit->room_id,

                // Parent lot info
                'lot_id' => $unit->lot_id,
                'lot_number' => $unit->lot->number ?? '-',
                'lot_imageUrl' => $unit->lot->image_url ?? null,
                'lot_unitPrice' => $unit->lot->unit_price ?? null,
                'organizer' => $unit->lot->organizer->name ?? '-',
                'organizer_id' => $unit->lot->organizer_id ?? null,
                'vendor' => $unit->lot->vendor->name ?? '-',
                'vendor_id' => $unit->lot->vendor_id ?? null,
                'lot_organizer' => $unit->lot->organizer->name ?? '-',
                'lot_vendor' => $unit->lot->vendor->name ?? '-',
                'lot_po_number' => $unit->lot->po_number ?? '-',
                'lot_date_of_receipt' => ($unit->lot && $unit->lot->date_of_receipt) ? $unit->lot->date_of_receipt->format('Y-m-d') : null,

                // Parent barang info
                'barang_id' => $barang->id ?? null,
                'barang_code' => $barang->number ?? '-',
                'barang_nama' => $barang->name ?? '-',
                'barang_brand' => $barang->brand->name ?? '-',
                'barang_specification' => $barang->specification ?? '-',
                'barang_category' => $barang->subcategory->category->name ?? '-',
                'barang_subcategory' => $barang->subcategory->name ?? '-',
                'barang_uom' => $barang->uom->name ?? '-',

                // Audit trails (lifecycles)
                'lifecycles' => $unit->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => $log->actor->name ?? '-',
                        'durasi' => $log->end_date && $log->start_date 
                            ? round($log->start_date->diffInMinutes($log->end_date) / 60, 1) . ' jam' 
                            : '-',
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ];
        });

        $locations = Location::orderBy('name')->get();
        $floors = Floor::with('location')->orderBy('name')->get();
        $rooms = Room::with('floor.location')->orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DaftarAset', [
            'user' => $request->user(),
            'units' => $units,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'projects' => $projects,
        ]);
    }
    /**
     * Menyimpan data unit (aset) baru ke dalam database.
     */
    public function store(Request $request)
    {
        $lot = Lot::with('barang.subcategory.category', 'organizer')->findOrFail($request->input('lot_id'));
        $subcategoryCode = $lot->barang->subcategory->code ?? '';
        $organizerCode = $lot->organizer->name ?? '';

        if ($subcategoryCode && $organizerCode) {
            $combination = "{$subcategoryCode}-{$organizerCode}-PTRE";
            $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');
            
            $count = Unit::where('number', 'like', "%-{$combination}-%")->count();
            $nextSerialVal = $count + 1;
            do {
                $serial = str_pad($nextSerialVal, 5, '0', STR_PAD_LEFT);
                $generatedNumber = "{$serial}-{$combination}-{$yy}";
                $exists = Unit::where('number', $generatedNumber)->exists();
                if ($exists) {
                    $nextSerialVal++;
                }
            } while ($exists);
            
            $request->merge(['number' => $generatedNumber]);
        }

        $rules = [
            'number' => 'required|string|max:25|unique:units,number',
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'status' => 'required|string|max:255',
            'condition' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'required_without:use_lot_image|nullable|image|max:1024',
            'use_lot_image' => 'nullable',
        ];
 
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
        $proposedStatus = $request->input('status');
        $needApproval = in_array($proposedStatus, $arrNeedApproval);

        if ($needApproval) {
            $rules['memo_file'] = 'required|file|max:2048';
            if ($proposedStatus === 'Hilang') {
                $rules['lost_doc_file'] = 'required|file|max:2048';
            }
        }

        $validated = $request->validate($rules);
 
        if ($needApproval) {
            $validated['status'] = 'Pending';
        }
 
        // Single creation logic
        if ($request->boolean('use_lot_image')) {
            if ($lot->image_url && Storage::disk('public')->exists($lot->image_url)) {
                $validated['image_url'] = $lot->image_url;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto LOT tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory', 'public');
            $validated['image_url'] = $imagePath;
        }
 
        unset($validated['use_lot_image']);
 
        $unit = Unit::create($validated);
 
        if ($needApproval) {
            $memoUrl = 'memos/placeholder.pdf';
            if ($request->hasFile('memo_file')) {
                $memoUrl = $request->file('memo_file')->store('memos', 'public');
            }
            $lostDocUrl = null;
            if ($proposedStatus === 'Hilang' && $request->hasFile('lost_doc_file')) {
                $lostDocUrl = $request->file('lost_doc_file')->store('lost_docs', 'public');
            }
            UnitStatusApproval::create([
                'unit_id' => $unit->id,
                'requester_id' => $request->user()->id,
                'proposed_status' => $proposedStatus,
                'previous_status' => 'Tersedia',
                'decision' => 'pending',
                'note' => null,
                'approver_id' => null,
                'requested_at' => now(),
                'memo_url' => $memoUrl,
                'lost_doc_url' => $lostDocUrl,
            ]);
        }
 
        return redirect()->back()->with('success', 'Aset berhasil ditambahkan.');
    }
 
    /**
     * Memperbarui data unit (aset) yang sudah ada di database.
     */
    public function update(Request $request, Unit $unit)
    {
        $rules = [
            'number' => 'required|string|max:25|unique:units,number,' . $unit->id,
            'lot_id' => 'required|exists:lots,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'status' => 'required|string|max:255',
            'condition' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|max:1024',
            'use_lot_image' => 'nullable',
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
        $proposedStatus = $request->input('status');
        $needApproval = in_array($proposedStatus, $arrNeedApproval);

        if ($needApproval) {
            $existing = UnitStatusApproval::where('unit_id', $unit->id)
                ->where('decision', 'pending')
                ->first();
            if (!$existing) {
                $rules['memo_file'] = 'required|file|max:2048';
                if ($proposedStatus === 'Hilang') {
                    $rules['lost_doc_file'] = 'required|file|max:2048';
                }
            }
        }

        $validated = $request->validate($rules);

        if ($request->boolean('use_lot_image')) {
            if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
                $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                    || Lot::where('image_url', $unit->image_url)->exists()
                    || Barang::where('image_url', $unit->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($unit->image_url);
                }
            }
            $lot = Lot::findOrFail($request->input('lot_id'));
            if ($lot->image_url && Storage::disk('public')->exists($lot->image_url)) {
                $validated['image_url'] = $lot->image_url;
            } else {
                $validated['image_url'] = null;
            }
        } else if ($request->hasFile('image_url')) {
            if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
                $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                    || Lot::where('image_url', $unit->image_url)->exists()
                    || Barang::where('image_url', $unit->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($unit->image_url);
                }
            }
            $imagePath = $request->file('image_url')->store('inventory', 'public');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        unset($validated['use_lot_image']);

        $previousStatus = $unit->status;

        if ($needApproval) {
            $validated['status'] = 'Pending';
        }

        $unit->update($validated);

        if ($needApproval) {
            $existing = UnitStatusApproval::where('unit_id', $unit->id)
                ->where('decision', 'pending')
                ->first();
            if (!$existing) {
                $memoUrl = 'memos/placeholder.pdf';
                if ($request->hasFile('memo_file')) {
                    $memoUrl = $request->file('memo_file')->store('memos', 'public');
                }
                $lostDocUrl = null;
                if ($proposedStatus === 'Hilang' && $request->hasFile('lost_doc_file')) {
                    $lostDocUrl = $request->file('lost_doc_file')->store('lost_docs', 'public');
                }
                UnitStatusApproval::create([
                    'unit_id' => $unit->id,
                    'requester_id' => $request->user()->id,
                    'proposed_status' => $proposedStatus,
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

        return redirect()->back()->with('success', 'Aset berhasil diperbarui.');
    }

    /**
     * Menghapus data unit (aset) dari database beserta gambarnya.
     */
    public function destroy(Unit $unit)
    {
        if (RequestUnitAssignment::where('unit_id', $unit->id)->exists()) {
            return redirect()->back()->with('error', 'Aset tidak dapat dihapus karena sudah memiliki riwayat peminjaman/permintaan.');
        }

        if ($unit->image_url && Storage::disk('public')->exists($unit->image_url)) {
            $isShared = Unit::where('image_url', $unit->image_url)->where('id', '!=', $unit->id)->exists()
                || Lot::where('image_url', $unit->image_url)->exists()
                || Barang::where('image_url', $unit->image_url)->exists();
            if (!$isShared) {
                Storage::disk('public')->delete($unit->image_url);
            }
        }

        $unit->delete();

        return redirect()->back()->with('success', 'Aset berhasil dihapus.');
    }
}
