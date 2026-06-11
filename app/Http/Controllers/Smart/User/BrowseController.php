<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Inventory\Barang;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Cart\ConsumableBasket;
use App\Models\Cart\AssetBasket;

class BrowseController extends Controller
{
    /**
     * Menampilkan halaman pencarian/katalog barang (Browse).
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('name')->get();
        
        $items = Subcategory::with(['category', 'barangs'])
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
                ];
            });

        return Inertia::render('Smart/User/Browse', [
            'user' => $request->user(),
            'items' => $items,
            'categories' => $categories,
        ]);
    }

    /**
     * Menambahkan barang ke dalam keranjang pengguna (baik barang pinjam maupun habis pakai).
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $barang = Barang::findOrFail($validated['barang_id']);
        $isConsumable = $barang->subcategory->category->is_consumable ?? true;
        $userId = $request->user()->id;

        if ($isConsumable) {
            // Find or create in consumable basket
            $basketItem = ConsumableBasket::firstOrNew([
                'user_id' => $userId,
                'barang_id' => $barang->id,
            ]);
            $basketItem->subcategory_id = $barang->subcategory_id;
            $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
            $basketItem->save();
        } else {
            // Find or create in asset basket
            $basketItem = AssetBasket::firstOrNew([
                'user_id' => $userId,
                'barang_id' => $barang->id,
            ]);
            $basketItem->subcategory_id = $barang->subcategory_id;
            $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
            // Assets need default start and end dates if not set, or we set them to tomorrow and the day after
            if (!$basketItem->start_date) {
                $basketItem->start_date = now()->addDay();
            }
            if (!$basketItem->end_date) {
                $basketItem->end_date = now()->addDays(2);
            }
            $basketItem->save();
        }

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang!');
    }
}
