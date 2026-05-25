<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Inventory\Barang;
use App\Models\Master\Category;
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
        
        $items = Barang::with(['subcategory.category', 'brand', 'uom'])
            ->get()
            ->map(function ($barang) {
                // Calculate stock of units with status 'tersedia'
                $stock = \App\Models\Inventory\Unit::whereHas('lot', function ($q) use ($barang) {
                    $q->where('barang_id', $barang->id);
                })->where('status', 'tersedia')->count();

                return [
                    'id' => $barang->id,
                    'code' => $barang->number,
                    'category' => ($barang->subcategory->category->name ?? '-') . ' (' . ($barang->subcategory->name ?? '-') . ')',
                    'category_id' => $barang->subcategory->category_id ?? null,
                    'category_name' => $barang->subcategory->category->name ?? '-',
                    'subcategory_name' => $barang->subcategory->name ?? '-',
                    'is_consumable' => (bool) ($barang->subcategory->category->is_consumable ?? true),
                    'brand' => $barang->brand->name ?? '-',
                    'spec' => $barang->specification,
                    'stock' => $stock,
                    'imageUrl' => $barang->image_url ? '/storage/' . $barang->image_url : null,
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
            $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
            $basketItem->save();
        } else {
            // Find or create in asset basket
            $basketItem = AssetBasket::firstOrNew([
                'user_id' => $userId,
                'barang_id' => $barang->id,
            ]);
            $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
            // Assets need a default start date if not set, or we set it to tomorrow
            if (!$basketItem->start_date) {
                $basketItem->start_date = now()->addDay();
            }
            $basketItem->save();
        }

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang!');
    }
}
