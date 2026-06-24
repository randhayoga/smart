<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Cart\ConsumableBasket;

class RequestCartController extends Controller
{
    /**
     * Menampilkan halaman Keranjang Habis Pakai.
     */
    public function index(Request $request)
    {
        $cartItems = ConsumableBasket::with(['barang.subcategory.category', 'barang.brand', 'subcategory.category'])
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($item) {
                // Calculate stock of units with status 'tersedia'
                if ($item->barang_id) {
                    $stock = \App\Models\Inventory\Unit::whereHas('lot', function ($q) use ($item) {
                        $q->where('barang_id', $item->barang_id);
                    })->where('status', 'tersedia')->count();
                } else {
                    $stock = \App\Models\Inventory\Unit::whereHas('lot.barang', function ($q) use ($item) {
                        $q->where('subcategory_id', $item->subcategory_id);
                    })->where('status', 'tersedia')->count();
                }

                return [
                    'id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'brand' => $item->barang?->brand->name ?? '-',
                    'name' => $item->barang?->name ?? 'Tidak Spesifik',
                    'spec' => $item->barang?->specification ?? '',
                    'category' => $item->barang 
                        ? ($item->barang->subcategory->category->name ?? '-') . ' (' . ($item->barang->subcategory->name ?? '-') . ')'
                        : ($item->subcategory->category->name ?? '-') . ' (' . ($item->subcategory->name ?? '-') . ')',
                    'code' => $item->barang?->number ?? '-',
                    'stock' => $stock,
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'imageUrl' => $item->barang?->image_url ? '/storage/' . $item->barang->image_url : null,
                ];
            });

        return Inertia::render('Smart/User/AssetCart', [
            'cartItems' => $cartItems,
        ]);
    }

    /**
     * Menambahkan barang ke dalam keranjang habis pakai.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'barang_id' => 'nullable|exists:barangs,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = $request->user()->id;

        // Find or create in consumable basket
        if (empty($validated['barang_id'])) {
            $basketItem = ConsumableBasket::firstOrNew([
                'user_id' => $userId,
                'subcategory_id' => $validated['subcategory_id'],
                'barang_id' => null,
            ]);
        } else {
            $basketItem = ConsumableBasket::firstOrNew([
                'user_id' => $userId,
                'subcategory_id' => null,
                'barang_id' => $validated['barang_id'],
            ]);
        }
        $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
        $basketItem->save();

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang!');
    }

    /**
     * Memperbarui kuantitas item keranjang habis pakai.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item = ConsumableBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->update(['quantity' => $validated['quantity']]);

        return redirect()->back()->with('success', 'Jumlah barang diperbarui.');
    }

    /**
     * Menghapus item dari keranjang habis pakai.
     */
    public function destroy(Request $request, $id)
    {
        $item = ConsumableBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->delete();

        return redirect()->back()->with('success', 'Barang dihapus dari keranjang.');
    }
}
