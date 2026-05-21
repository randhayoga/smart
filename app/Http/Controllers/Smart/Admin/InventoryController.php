<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Display the inventory management page.
     */
    public function index(Request $request): Response
    {
        $categories = \App\Models\Master\Category::orderBy('code')->get();
        $subcategories = \App\Models\Master\Subcategory::with('category')->orderBy('code')->get();
        $brands = \App\Models\Master\Brand::orderBy('name')->get();
        $uoms = \App\Models\Master\Uom::orderBy('name')->get();
        
        $barangs = \App\Models\Inventory\Barang::with(['subcategory.category', 'brand', 'uom'])
            ->get()
            ->map(function ($barang) {
                // Calculate amount by summing units in each lot
                $amount = $barang->lots()->withCount('units')->get()->sum('units_count');
                
                return [
                    'id' => $barang->id,
                    'code' => $barang->number,
                    'category' => $barang->subcategory->category->name ?? '-',
                    'subcategory' => $barang->subcategory->name ?? '-',
                    'brand' => $barang->brand->name ?? '-',
                    'specification' => $barang->specification,
                    'lastUpdate' => $barang->updated_at ? $barang->updated_at->format('d-m-Y H:i') : '-',
                    'amount' => $amount,
                    'image_url' => $barang->image_url,
                    'uom' => $barang->uom->name ?? '-',
                    'subcategory_id' => $barang->subcategory_id,
                    'category_id' => $barang->subcategory->category_id ?? null,
                    'brand_id' => $barang->brand_id,
                    'uom_id' => $barang->uom_id,
                ];
            });

        return Inertia::render('Smart/Admin/ManajemenStok', [
            'user' => $request->user(),
            'categories' => $categories,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'uoms' => $uoms,
            'barangs' => $barangs,
        ]);
    }

    /**
     * Display the inventory item detail page.
     */
    public function show(Request $request, string $id): Response
    {
        return Inertia::render('Smart/Admin/ManajemenStokDetail', [
            'user' => $request->user(),
            'itemId' => $id,
        ]);
    }
}
