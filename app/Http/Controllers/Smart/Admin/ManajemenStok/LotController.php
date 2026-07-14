<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LotController extends Controller
{
    /**
     * Menyimpan data LOT baru ke dalam database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:26|unique:lots,number',
            'barang_id' => 'required|exists:barangs,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => 'required|exists:vendors,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'initial_quantity' => 'nullable|integer|min:0|max:2147483647',
            'current_quantity' => 'nullable|integer|min:0|max:2147483647',
            'po_number' => 'required|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'required_without:use_parent_image|nullable|image|max:1024',
            'use_parent_image' => 'nullable',
            'auto_create_assets' => 'nullable|boolean',
            'auto_create_assets_count' => 'required_if:auto_create_assets,true|nullable|integer|min:1|max:999',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => 'required_if:burden,Project|nullable|exists:tb_projects,id',
        ], [
            'initial_quantity.integer' => 'Tidak boleh desimal.',
            'current_quantity.integer' => 'Tidak boleh desimal.',
            'auto_create_assets_count.integer' => 'Tidak boleh desimal.',
        ]);

        if ($request->boolean('use_parent_image')) {
            $barang = Barang::findOrFail($request->input('barang_id'));
            if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                $validated['image_url'] = $barang->image_url;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto barang parent tidak ditemukan di storage.']);
            }
        } else {
            $imagePath = $request->file('image_url')->store('inventory', 'public');
            $validated['image_url'] = $imagePath;
        }

        unset($validated['use_parent_image']);
        unset($validated['auto_create_assets']);
        unset($validated['auto_create_assets_count']);
        $validated['initial_quantity'] = $validated['initial_quantity'] ?? 0;
        $validated['burden'] = $validated['burden'] ?? 'Corporate';
        $validated['project_id'] = ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null;

        Lot::create($validated);

        return redirect()->back()->with('success', 'LOT berhasil ditambahkan.');    
    }

    /**
     * Memperbarui data LOT yang sudah ada di database.
     */
    public function update(Request $request, Lot $lot)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:26|unique:lots,number,' . $lot->id,
            'barang_id' => 'required|exists:barangs,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => 'required|exists:vendors,id',
            'location_id' => 'required|exists:locations,id',
            'floor_id' => 'nullable|exists:floors,id',
            'room_id' => 'nullable|exists:rooms,id',
            'initial_quantity' => 'nullable|integer|min:0|max:2147483647',
            'current_quantity' => 'nullable|integer|min:0|max:2147483647',
            'po_number' => 'required|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|max:1024',
            'use_parent_image' => 'nullable',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => 'required_if:burden,Project|nullable|exists:tb_projects,id',
        ], [
            'initial_quantity.integer' => 'Tidak boleh desimal.',
            'current_quantity.integer' => 'Tidak boleh desimal.',
        ]);

        if ($request->boolean('use_parent_image')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || Barang::where('image_url', $lot->image_url)->exists()
                    || Unit::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($lot->image_url);
                }
            }
            $barang = Barang::findOrFail($request->input('barang_id'));
            if ($barang->image_url && Storage::disk('public')->exists($barang->image_url)) {
                $validated['image_url'] = $barang->image_url;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto barang parent tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || Barang::where('image_url', $lot->image_url)->exists()
                    || Unit::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('public')->delete($lot->image_url);
                }
            }
            $imagePath = $request->file('image_url')->store('inventory', 'public');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        unset($validated['use_parent_image']);
        $validated['initial_quantity'] = $validated['initial_quantity'] ?? 0;
        $validated['burden'] = $validated['burden'] ?? 'Corporate';
        $validated['project_id'] = ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null;

        $lot->update($validated);

        return redirect()->back()->with('success', 'LOT berhasil diperbarui.');
    }

    /**
     * Menghapus data LOT dari database beserta gambarnya.
     */
    public function destroy(Lot $lot)
    {
        if ($lot->units()->exists()) {
            return redirect()->back()->with('error', 'LOT tidak dapat dihapus karena masih memiliki unit terkait.');
        }

        if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('public')->exists($lot->image_url)) {
            $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                || Barang::where('image_url', $lot->image_url)->exists()
                || Unit::where('image_url', $lot->image_url)->exists();
            if (!$isShared) {
                Storage::disk('public')->delete($lot->image_url);
            }
        }
        $lot->delete();

        return redirect()->back()->with('success', 'LOT berhasil dihapus.');
    }

    /**
     * Menampilkan detail data LOT dalam format JSON atau render halaman Inertia.
     */
    public function show(Request $request, Lot $lot)
    {
        $lot->load([
            'barang.subcategory.category',
            'barang.brand',
            'barang.uom',
            'organizer',
            'vendor',
            'location',
            'floor',
            'room',
            'project',
        ]);

        if ($request->wantsJson() && !$request->headers->has('X-Inertia')) {
            return response()->json([
                'id' => $lot->id,
                'number' => $lot->number,
                'barang_id' => $lot->barang_id,
                'po_number' => $lot->po_number,
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('Y-m-d') : null,
                'organizer' => $lot->organizer->name ?? '-',
                'organizer_id' => $lot->organizer_id,
                'vendor' => $lot->vendor->name ?? '-',
                'vendor_id' => $lot->vendor_id,
                'location' => $lot->location->name ?? '-',
                'location_id' => $lot->location_id,
                'floor' => $lot->floor->name ?? null,
                'floor_id' => $lot->floor_id,
                'room' => $lot->room->name ?? null,
                'room_id' => $lot->room_id,
                'unitPrice' => $lot->unit_price,
                'imageUrl' => $lot->image_url,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d/m/Y H:i') : '-',
                'age' => $lot->age,
                
                // Parent barang info
                'barang_code' => $lot->barang->number ?? '-',
                'barang_brand' => $lot->barang->brand->name ?? '-',
                'barang_nama' => $lot->barang->name ?? '-',
                'barang_specification' => $lot->barang->specification ?? '-',
                'barang_category' => $lot->barang->subcategory->category->name ?? '-',
                'barang_subcategory' => $lot->barang->subcategory->name ?? '-',
                'barang_subcategory_code' => $lot->barang->subcategory->code ?? '-',
                'barang_uom' => $lot->barang->uom->name ?? '-',
            ]);
        }

        // Ambil data unit (aset) terkait LOT ini
        $units = Unit::with([
            'location', 'floor', 'room', 'statusApprovals',
            'lot.barang.subcategory.category', 'lot.barang.brand', 'lot.barang.uom',
            'lot.organizer', 'lot.vendor', 'lifecycles.actor'
        ])
        ->where('lot_id', $lot->id)
        ->get()
        ->map(function ($unit) {
            $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
            $approvedApproval = $unit->status === 'Dihapus' 
                ? $unit->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;
            $barang = $unit->lot->barang ?? null;
            return [
                'id' => $unit->id,
                'number' => $unit->number,
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
                'lot_age' => $unit->lot->age ?? null,

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

        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        $vendors = Vendor::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $floors = Floor::with('location')->orderBy('name')->get();
        $rooms = Room::with('floor.location')->orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();

        return Inertia::render('Smart/Admin/ManajemenStok/DetailLOTNonConsumables', [
            'lot' => [
                'id' => $lot->id,
                'number' => $lot->number,
                'barang_id' => $lot->barang_id,
                'po_number' => $lot->po_number,
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('Y-m-d') : null,
                'organizer' => $lot->organizer->name ?? '-',
                'organizer_id' => $lot->organizer_id,
                'vendor' => $lot->vendor->name ?? '-',
                'vendor_id' => $lot->vendor_id,
                'location' => $lot->location->name ?? '-',
                'location_id' => $lot->location_id,
                'floor' => $lot->floor->name ?? null,
                'floor_id' => $lot->floor_id,
                'room' => $lot->room->name ?? null,
                'room_id' => $lot->room_id,
                'unitPrice' => $lot->unit_price,
                'imageUrl' => $lot->image_url,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d/m/Y H:i') : '-',
                'age' => $lot->age,
                
                // Parent barang info
                'barang_id' => $lot->barang->id ?? null,
                'barang_code' => $lot->barang->number ?? '-',
                'barang_brand' => $lot->barang->brand->name ?? '-',
                'barang_nama' => $lot->barang->name ?? '-',
                'barang_specification' => $lot->barang->specification ?? '-',
                'barang_category' => $lot->barang->subcategory->category->name ?? '-',
                'barang_subcategory' => $lot->barang->subcategory->name ?? '-',
                'barang_subcategory_code' => $lot->barang->subcategory->code ?? '-',
                'barang_uom' => $lot->barang->uom->name ?? '-',
            ],
            'units' => $units,
            'brands' => $brands,
            'uoms' => $uoms,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'floors' => $floors,
            'rooms' => $rooms,
            'projects' => $projects,
        ]);
    }
}
