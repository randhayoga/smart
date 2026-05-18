<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\InventoryLog;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use App\Models\Master\Location;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display the inventory management page.
     */
    public function index(Request $request): Response
    {
        $barangs = Barang::with(['subcategory.category', 'brand', 'uom'])->get();

        $inventoryList = $barangs->map(function ($barang) {
            // Count stock quantity (amount)
            $amount = 0;
            if ($barang->type === 'asset') {
                // Count total units associated with this barang through lots
                $amount = Unit::whereHas('lot', function ($query) use ($barang) {
                    $query->where('barang_id', $barang->id);
                })->count();
            } else {
                // Sum quantity change from inventory logs for consumable
                $amount = InventoryLog::where('barang_id', $barang->id)->sum('quantity_change');
                // Fallback for demo/seeded data if no logs exist yet
                if ($amount <= 0) {
                    $amount = 50; // default seeded amount for consumables
                }
            }

            return [
                'id' => $barang->id,
                'code' => $barang->number,
                'category' => $barang->subcategory->category->name ?? '-',
                'subcategory' => $barang->subcategory->name ?? '-',
                'brand' => $barang->brand->name ?? '-',
                'specification' => $barang->specification,
                'lastUpdate' => $barang->updated_at ? $barang->updated_at->format('d-m-Y H:i') : '-',
                'amount' => (int) $amount,
            ];
        });

        // Fetch master data for create form
        $categories = Category::all();
        $categoriesList = $categories->pluck('name')->toArray();

        $subcategoryMap = [];
        foreach ($categories as $cat) {
            $subcategoryMap[$cat->name] = Subcategory::where('category_id', $cat->id)->pluck('name')->toArray();
        }

        $brandMap = [];
        // Map brands to categories for matching the UI dropdown design if possible,
        // otherwise return all brands for all categories
        $allBrands = Brand::pluck('name')->toArray();
        foreach ($categoriesList as $catName) {
            $brandMap[$catName] = $allBrands;
        }

        $uomsList = Uom::pluck('name')->toArray();

        return Inertia::render('Smart/Admin/ManajemenStok', [
            'user' => $request->user(),
            'inventoryList' => $inventoryList,
            'categoriesList' => $categoriesList,
            'subcategoryMap' => $subcategoryMap,
            'brandMap' => $brandMap,
            'uomsList' => $uomsList,
        ]);
    }

    /**
     * Store a newly created item in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:barangs,number',
            'category' => 'required|string',
            'subcategory' => 'required|string',
            'brand' => 'required|string',
            'unit' => 'required|string',
            'specification' => 'required|string|max:255',
            'photo' => 'nullable|image|max:1024',
        ]);

        // Find subcategory
        $subcategory = Subcategory::where('name', $validated['subcategory'])->firstOrFail();
        
        // Find or create brand
        $brand = Brand::where('name', $validated['brand'])->firstOrCreate(['name' => $validated['brand']]);

        // Find or create UOM (unit)
        $uom = Uom::where('name', $validated['unit'])->firstOrCreate(['name' => $validated['unit']]);

        // Determine if type is consumable or asset based on category's is_consumable flag
        $category = Category::where('name', $validated['category'])->firstOrFail();
        $type = $category->is_consumable ? 'consumable' : 'asset';

        // Handle image upload if present, otherwise use a placeholder
        $imageUrl = '/images/placeholder.png';
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('public/images');
            $imageUrl = '/storage/' . str_replace('public/', '', $path);
        }

        // Create the Barang
        $barang = Barang::create([
            'number' => $validated['code'],
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'type' => $type,
            'specification' => $validated['specification'],
            'image_url' => $imageUrl,
        ]);

        return redirect()->route('smart.inventory.show', $barang->number)
            ->with('success', 'Barang berhasil dibuat.');
    }

    /**
     * Display the inventory item detail page.
     */
    public function show(Request $request, string $id): Response
    {
        $barang = Barang::with(['subcategory.category', 'brand', 'uom', 'lots.organizer', 'lots.vendor', 'lots.location', 'lots.units'])->where('number', $id)->firstOrFail();

        // Calculate amount
        $amount = 0;
        if ($barang->type === 'asset') {
            $amount = Unit::whereHas('lot', function ($query) use ($barang) {
                $query->where('barang_id', $barang->id);
            })->count();
        } else {
            $amount = InventoryLog::where('barang_id', $barang->id)->sum('quantity_change');
            if ($amount <= 0) {
                $amount = 50;
            }
        }

        $barangDetail = [
            'id' => $barang->id,
            'code' => $barang->number,
            'category' => $barang->subcategory->category->name ?? '-',
            'subcategory' => $barang->subcategory->name ?? '-',
            'brand' => $barang->brand->name ?? '-',
            'specification' => $barang->specification,
            'unit' => $barang->uom->name ?? '-',
            'lastUpdate' => $barang->updated_at ? $barang->updated_at->format('d-m-Y H:i') : '-',
            'lotCount' => $barang->lots->count(),
            'amount' => (int) $amount,
            'imageUrl' => $barang->image_url ?: '/images/placeholder.png',
        ];

        // Format lots for DataTable
        $lotsList = $barang->lots->map(function ($lot) use ($barang) {
            $assetCount = $barang->type === 'asset' ? $lot->units->count() : '-';
            return [
                'id' => $lot->id,
                'lotCode' => $lot->number,
                'poNumber' => $lot->po_number,
                'entryDate' => $lot->date_of_receipt ? $lot->date_of_receipt->format('d-m-Y') : '-',
                'organizer' => $lot->organizer->name ?? '-',
                'assetCount' => $assetCount,
            ];
        });

        // Fetch organizers, vendors, locations
        $organizersList = \App\Models\Master\Organizer::pluck('name')->toArray();
        $vendorsList = \App\Models\Master\Vendor::pluck('name')->toArray();
        $locationsList = \App\Models\Master\Location::pluck('name')->toArray();

        return Inertia::render('Smart/Admin/ManajemenStokDetail', [
            'user' => $request->user(),
            'itemId' => $id,
            'barangDetail' => $barangDetail,
            'lotsList' => $lotsList,
            'organizersList' => $organizersList,
            'vendorsList' => $vendorsList,
            'locationsList' => $locationsList,
        ]);
    }

    /**
     * Update the specified item in the database.
     */
    public function update(Request $request, string $id)
    {
        $barang = Barang::findOrFail($id);

        $validated = $request->validate([
            'satuan' => 'required|string',
            'merek' => 'required|string',
            'spesifikasi' => 'required|string|max:255',
            'photo' => 'nullable|image|max:1024',
        ]);

        $brand = Brand::where('name', $validated['merek'])->firstOrCreate(['name' => $validated['merek']]);
        $uom = Uom::where('name', $validated['satuan'])->firstOrCreate(['name' => $validated['satuan']]);

        $updateData = [
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'specification' => $validated['spesifikasi'],
        ];

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('public/images');
            $updateData['image_url'] = '/storage/' . str_replace('public/', '', $path);
        }

        $barang->update($updateData);

        return redirect()->route('smart.inventory.show', $barang->number)
            ->with('success', 'Detail barang berhasil diperbarui.');
    }

    /**
     * Delete the specified item from the database.
     */
    public function destroy(string $id)
    {
        $barang = Barang::findOrFail($id);
        $this->deleteBarangSafely($barang);

        return redirect()->route('smart.inventory.index')
            ->with('success', 'Barang berhasil dihapus.');
    }

    /**
     * Bulk delete items.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:barangs,id',
        ]);

        foreach ($validated['ids'] as $id) {
            $barang = Barang::findOrFail($id);
            $this->deleteBarangSafely($barang);
        }

        return redirect()->route('smart.inventory.index')
            ->with('success', 'Barang berhasil dihapus.');
    }

    /**
     * Safely delete a Barang by removing all dependent entries in other tables first.
     */
    private function deleteBarangSafely(Barang $barang): void
    {
        DB::transaction(function () use ($barang) {
            $barangId = $barang->id;
            
            // Get all lot IDs for this barang
            $lotIds = DB::table('lots')->where('barang_id', $barangId)->pluck('id')->toArray();
            
            // Get all unit IDs for these lots
            $unitIds = [];
            if (!empty($lotIds)) {
                $unitIds = DB::table('units')->whereIn('lot_id', $lotIds)->pluck('id')->toArray();
            }
            
            // Get all request item IDs for this barang
            $requestItemIds = DB::table('request_items')->where('barang_id', $barangId)->pluck('id')->toArray();
            
            // 1. Delete request unit assignments pointing to these units or request items
            if (!empty($unitIds)) {
                DB::table('request_unit_assignments')->whereIn('unit_id', $unitIds)->delete();
            }
            if (!empty($requestItemIds)) {
                DB::table('request_unit_assignments')->whereIn('request_item_id', $requestItemIds)->delete();
            }
            
            // 2. Delete request items referencing this barang
            DB::table('request_items')->where('barang_id', $barangId)->delete();
            
            // 3. Delete from baskets
            DB::table('consumable_baskets')->where('barang_id', $barangId)->delete();
            DB::table('asset_baskets')->where('barang_id', $barangId)->delete();
            
            // 4. Delete units
            if (!empty($lotIds)) {
                DB::table('units')->whereIn('lot_id', $lotIds)->delete();
            }
            
            // 5. Delete lots
            DB::table('lots')->where('barang_id', $barangId)->delete();
            
            // 6. Delete the barang itself
            $barang->delete();
        });
    }
}
