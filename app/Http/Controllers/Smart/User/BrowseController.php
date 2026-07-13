<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\Inventory\Barang;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrowseController extends Controller
{
    /**
     * Menampilkan halaman pencarian/katalog barang (Browse).
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('name')->get();
        
        $items = Subcategory::with(['category', 'barangs.brand', 'barangs.uom'])
            ->get()
            ->map(function ($subcategory) {
                $firstBarang = $subcategory->barangs->first();

                return [
                    'id' => $subcategory->id,
                    'barang_id' => $firstBarang ? $firstBarang->id : null,
                    'code' => $firstBarang ? $firstBarang->number : '-',
                    'category' => $subcategory->category->name ?? '-',
                    'category_id' => $subcategory->category_id,
                    'category_name' => $subcategory->category->name ?? '-',
                    'subcategory_name' => $subcategory->name,
                    'is_consumable' => (bool) ($subcategory->category->is_consumable ?? true),
                    'brand' => $firstBarang && $firstBarang->brand ? $firstBarang->brand->name : '-',
                    'name' => $subcategory->name,
                    'spec' => $firstBarang ? $firstBarang->specification : '-',
                    'stock' => 0,
                    'imageUrl' => $firstBarang && $firstBarang->image_url ? '/storage/' . $firstBarang->image_url : null,
                    'uom' => $firstBarang && $firstBarang->uom ? $firstBarang->uom->name : 'satuan',
                    'barangs' => $subcategory->barangs->map(function ($barang) {
                        return [
                            'id' => $barang->id,
                            'number' => $barang->number,
                            'name' => $barang->name,
                            'brand' => $barang->brand ? $barang->brand->name : '-',
                            'specification' => $barang->specification,
                            'imageUrl' => $barang->image_url ? '/storage/' . $barang->image_url : null,
                            'uom' => $barang->uom ? $barang->uom->name : 'satuan',
                        ];
                    }),
                ];
            });

        return Inertia::render('Smart/User/Browse', [
            'user' => $request->user(),
            'items' => $items,
            'categories' => $categories,
        ]);
    }

}
