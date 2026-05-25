<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Cart\ConsumableBasket;

class AssetCartController extends Controller
{
    /**
     * Menampilkan halaman Keranjang Habis Pakai.
     */
    public function index(Request $request)
    {
        $cartItems = ConsumableBasket::with(['barang.subcategory.category', 'barang.brand'])
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($item) {
                // Calculate stock of units with status 'tersedia'
                $stock = \App\Models\Inventory\Unit::whereHas('lot', function ($q) use ($item) {
                    $q->where('barang_id', $item->barang_id);
                })->where('status', 'tersedia')->count();

                return [
                    'id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'brand' => $item->barang->brand->name ?? '-',
                    'spec' => $item->barang->specification ?? '',
                    'category' => ($item->barang->subcategory->category->name ?? '-') . ' (' . ($item->barang->subcategory->name ?? '-') . ')',
                    'code' => $item->barang->number ?? '',
                    'stock' => $stock,
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'imageUrl' => $item->barang->image_url ? '/storage/' . $item->barang->image_url : null,
                ];
            });

        return Inertia::render('Smart/User/AssetCart', [
            'cartItems' => $cartItems,
        ]);
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
