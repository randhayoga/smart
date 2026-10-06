<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Services\InventoryLogService;
use App\Services\Inventory\UnitNumberService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Lot Controller managing inventory lot batches, procurement tracking, project burden allocation, and lot detail views.
 */
class LotController extends Controller
{
    /**
     * Store a newly created LOT batch in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:26|unique:lots,number',
            'barang_id' => 'required|exists:barangs,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => 'nullable|integer', // Target DB: eproc (vendors table) - not operational yet
            'legacy_vendor_id' => 'nullable|exists:vendors,id', // Legacy vendors in local DB
            'location_id' => 'required|exists:locations,id',
            'initial_quantity' => 'nullable|integer|min:0|max:2147483647',
            'current_quantity' => 'nullable|integer|min:0|max:2147483647',
            'po_number' => 'nullable|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|max:1024',
            'use_parent_image' => 'nullable',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => ['required_if:burden,Project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
        ], [
            'initial_quantity.integer' => 'Tidak boleh desimal.',
            'current_quantity.integer' => 'Tidak boleh desimal.',
        ]);

        if ($request->boolean('use_parent_image')) {
            $barang = Barang::findOrFail($request->input('barang_id'));
            if ($barang->image_url && Storage::disk('local')->exists($barang->image_url)) {
                $validated['image_url'] = $barang->image_url;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto barang parent tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            $imagePath = $request->file('image_url')->store('inventory', 'local');
            $validated['image_url'] = $imagePath;
        } else {
            $validated['image_url'] = null;
        }

        $barang = Barang::findOrFail($request->input('barang_id'));
        $isConsumable = (bool) $barang->is_consumable;

        unset($validated['use_parent_image']);
        $validated['initial_quantity'] = $validated['initial_quantity'] ?? 0;
        if ($isConsumable) {
            $validated['burden'] = $validated['burden'] ?? 'Corporate';
            $validated['project_id'] = ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null;
        } else {
            $validated['burden'] = null;
            $validated['project_id'] = null;
        }
        $validated['vendor_id'] = !empty($validated['vendor_id']) ? (int)$validated['vendor_id'] : null;
        $validated['legacy_vendor_id'] = !empty($validated['legacy_vendor_id']) ? (int)$validated['legacy_vendor_id'] : null;

        $lot = Lot::create($validated);

        if ($request->user()) {
            app(InventoryLogService::class)->logLotCreated($lot, $request->user());
        }

        if ($lot->barang) {
            app(NotificationService::class)->checkAndNotifyLowStock($lot->barang);
        }

        return redirect()->back()
            ->with('success', __('inventory.lot_created'));
    }

    /**
     * Update the specified LOT batch in storage.
     */
    public function update(Request $request, Lot $lot)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:26|unique:lots,number,' . $lot->id,
            'barang_id' => 'required|exists:barangs,id',
            'organizer_id' => 'required|exists:organizers,id',
            'vendor_id' => 'nullable|integer', // Target DB: eproc (vendors table) - not operational yet
            'legacy_vendor_id' => 'nullable|exists:vendors,id', // Legacy vendors in local DB
            'location_id' => 'required|exists:locations,id',
            'initial_quantity' => 'nullable|integer|min:0|max:2147483647',
            'po_number' => 'nullable|string|max:255',
            'date_of_receipt' => 'required|date',
            'unit_price' => 'nullable|numeric|min:0|max:999999999.99',
            'image_url' => 'nullable|image|max:1024',
            'delete_image' => 'nullable|boolean',
            'use_parent_image' => 'nullable',
            'burden' => 'nullable|string|in:Corporate,Project',
            'project_id' => ['required_if:burden,Project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
        ], [
            'initial_quantity.integer' => 'Tidak boleh desimal.',
        ]);

        if ($request->boolean('delete_image')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('local')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || Barang::where('image_url', $lot->image_url)->exists()
                    || Unit::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($lot->image_url);
                }
            }
            $validated['image_url'] = null;
        } else if ($request->boolean('use_parent_image')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('local')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || Barang::where('image_url', $lot->image_url)->exists()
                    || Unit::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($lot->image_url);
                }
            }
            $barang = Barang::findOrFail($request->input('barang_id'));
            if ($barang->image_url && Storage::disk('local')->exists($barang->image_url)) {
                $validated['image_url'] = $barang->image_url;
            } else {
                return redirect()->back()->withErrors(['image_url' => 'Foto barang parent tidak ditemukan di storage.']);
            }
        } else if ($request->hasFile('image_url')) {
            if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('local')->exists($lot->image_url)) {
                $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                    || Barang::where('image_url', $lot->image_url)->exists()
                    || Unit::where('image_url', $lot->image_url)->exists();
                if (!$isShared) {
                    Storage::disk('local')->delete($lot->image_url);
                }
            }
            $imagePath = $request->file('image_url')->store('inventory', 'local');
            $validated['image_url'] = $imagePath;
        } else {
            unset($validated['image_url']);
        }

        unset($validated['use_parent_image']);
        unset($validated['delete_image']);
        if (!$request->has('initial_quantity')) {
            unset($validated['initial_quantity']);
        }
        $isConsumable = (bool) ($lot->barang?->is_consumable ?? false);
        if ($isConsumable) {
            $validated['burden'] = $validated['burden'] ?? $lot->burden ?? 'Corporate';
            $validated['project_id'] = ($validated['burden'] === 'Project') ? ($validated['project_id'] ?? null) : null;
        } else {
            $validated['burden'] = null;
            $validated['project_id'] = null;
        }
        if ($request->has('vendor_id')) {
            $validated['vendor_id'] = !empty($validated['vendor_id']) ? (int)$validated['vendor_id'] : null;
        }
        if ($request->has('legacy_vendor_id')) {
            $validated['legacy_vendor_id'] = !empty($validated['legacy_vendor_id']) ? (int)$validated['legacy_vendor_id'] : null;
        }

        $original = $lot->getAttributes();
        $lot->update($validated);

        if ($request->user()) {
            app(InventoryLogService::class)->logLotUpdated($lot, $original, $request->user());
        }

        if ($lot->barang) {
            app(NotificationService::class)->checkAndNotifyLowStock($lot->barang);
        }

        return redirect()->back()->with('success', __('inventory.lot_updated'));
    }

    /**
     * Remove the specified LOT batch from storage along with its stored image.
     */
    public function destroy(Request $request, Lot $lot)
    {
        if ($lot->units()->exists()) {
            return redirect()->back()->with('error', __('inventory.lot_cannot_delete_has_units'));
        }

        if ($lot->image_url && $lot->image_url !== 'inventory/lots/placeholder.jpg' && Storage::disk('local')->exists($lot->image_url)) {
            $isShared = Lot::where('image_url', $lot->image_url)->where('id', '!=', $lot->id)->exists()
                || Barang::where('image_url', $lot->image_url)->exists()
                || Unit::where('image_url', $lot->image_url)->exists();
            if (!$isShared) {
                Storage::disk('local')->delete($lot->image_url);
            }
        }

        if ($request->user()) {
            app(InventoryLogService::class)->prepareAndLogLotDeleted($lot, $request->user());
        }

        $barang = $lot->barang;
        $lot->delete();

        if ($barang) {
            app(NotificationService::class)->checkAndNotifyLowStock($barang);
        }

        return redirect()->back()->with('success', __('inventory.lot_deleted'));
    }

    /**
     * Display detailed LOT batch information in JSON format or render Inertia page.
     */
    public function show(Request $request, Lot $lot)
    {
        $lot->load([
            'barang.subcategory.category',
            'barang.brand',
            'barang.uom',
            'organizer',
            'vendor',
            'legacyVendor',
            'location.parent',
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
                'vendor' => $lot->vendor_name,
                'vendor_id' => $lot->vendor_id,
                'legacy_vendor_id' => $lot->legacy_vendor_id,
                'location' => $lot->location ? $lot->location->full_name : '-',
                'location_id' => $lot->location_id,
                'unitPrice' => $lot->unit_price,
                'imageUrl' => $lot->image_url,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
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
                'barang_min_stock_threshold' => $lot->barang->min_stock_threshold ?? null,
                'next_asset_code' => app(UnitNumberService::class)->generateUnitNumber($lot),
            ]);
        }

        // Retrieve unit (asset) items associated with this LOT
        $units = Unit::with([
            'location.parent', 'statusApprovals', 'project',
            'lot.barang.subcategory.category', 'lot.barang.brand', 'lot.barang.uom',
            'lot.organizer', 'lot.vendor', 'lot.legacyVendor', 'lifecycles.actor'
        ])
        ->where('lot_id', $lot->id)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($unit) {
            $pendingApproval = $unit->statusApprovals->firstWhere('decision', 'pending');
            $approvedApproval = $unit->status === 'Tidak Aktif' 
                ? $unit->statusApprovals->where('decision', 'approved')->sortByDesc('updated_at')->first() 
                : null;
            $barang = $unit->lot->barang ?? null;
            return [
                'id' => $unit->id,
                'number' => $unit->number,
                'legacy_number' => $unit->legacy_number,
                'status' => $unit->status,
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
                'condition' => $unit->condition,
                'type' => $unit->type,
                'classification' => $unit->classification,
                'price' => $unit->price,
                'image_url' => $unit->image_url,
                'vehicle_registration' => $unit->vehicle_registration,
                'burden' => $unit->burden,
                'project_id' => $unit->project_id,
                'project_name' => $unit->project ? $unit->project->project_name : null,
                'project_no' => $unit->project ? $unit->project->no_project : null,
                'created_at' => $unit->created_at?->toIso8601String(),
                'updated_at' => $unit->updated_at ? $unit->updated_at->format('d-m-Y H:i') : '-',
                
                // Location info
                'location' => $unit->location ? $unit->location->full_name : '-',
                'location_id' => $unit->location_id,

                // Parent lot info
                'lot_id' => $unit->lot_id,
                'lot_number' => $unit->lot->number ?? '-',
                'lot_imageUrl' => $unit->lot->image_url ?? null,
                'lot_unitPrice' => $unit->lot->unit_price ?? null,
                'organizer' => $unit->lot->organizer->name ?? '-',
                'organizer_id' => $unit->lot->organizer_id ?? null,
                'vendor' => $unit->lot?->vendor_name ?? '-',
                'vendor_id' => $unit->lot->vendor_id ?? null,
                'legacy_vendor_id' => $unit->lot->legacy_vendor_id ?? null,
                'lot_organizer' => $unit->lot->organizer->name ?? '-',
                'lot_vendor' => $unit->lot?->vendor_name ?? '-',
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

                // Active borrowing info
                'active_borrowing' => $unit->active_borrowing,

                // Audit trails (lifecycles)
                'lifecycles' => $unit->lifecycles->map(function ($log) {
                    return [
                        'waktu' => $log->start_date ? $log->start_date->format('d-m-Y H:i:s') : '-',
                        'status' => $log->status,
                        'action_type' => $log->action_type,
                        'aktor' => $log->actor->name ?? '-',
                        'durasi' => $log->formatted_duration,
                        'catatan' => $log->note ?? '-',
                    ];
                })->toArray(),
            ];
        });

        $brands = Brand::orderBy('name')->get();
        $uoms = Uom::orderBy('name')->get();
        $organizers = Organizer::orderBy('name')->get();
        // Target DB: eproc (vendors table).
        // Eproc database is not operational yet, returning empty array for LOT vendor selection.
        $vendors = []; // When operational: DB::connection('eproc')->table('vendors')->select('id', 'name')->orderBy('name')->get();
        $locations = Location::with('parent')->active()->orderBy('name')->get();
        $projects = TbProject::orderBy('project_name')->get();
        $users = \App\Models\User::select('id', 'employee_name', 'employee_id')
            ->where('active', 1)
            ->orderBy('employee_name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => "{$u->employee_name} ({$u->employee_id})",
            ]);

        return Inertia::render('Smart/Admin/ManajemenStok/DetailLOTNonConsumables', [
            'lot' => [
                'id' => $lot->id,
                'number' => $lot->number,
                'barang_id' => $lot->barang_id,
                'po_number' => $lot->po_number,
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('Y-m-d') : null,
                'organizer' => $lot->organizer->name ?? '-',
                'organizer_id' => $lot->organizer_id,
                'vendor' => $lot->vendor_name,
                'vendor_id' => $lot->vendor_id,
                'legacy_vendor_id' => $lot->legacy_vendor_id,
                'location' => $lot->location ? $lot->location->full_name : '-',
                'location_id' => $lot->location_id,
                'unitPrice' => $lot->unit_price,
                'imageUrl' => $lot->image_url,
                'initial_quantity' => $lot->initial_quantity,
                'current_quantity' => $lot->current_quantity,
                'burden' => $lot->burden,
                'project_id' => $lot->project_id,
                'project_name' => $lot->project ? $lot->project->project_name : null,
                'project_no' => $lot->project ? $lot->project->no_project : null,
                'updated_at' => $lot->updated_at ? $lot->updated_at->format('d-m-Y H:i') : '-',
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
                'next_asset_code' => app(UnitNumberService::class)->generateUnitNumber($lot),
            ],
            'units' => $units,
            'brands' => $brands,
            'uoms' => $uoms,
            'organizers' => $organizers,
            'vendors' => $vendors,
            'locations' => $locations,
            'projects' => $projects,
            'users' => $users,
        ]);
    }
}
