<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Cart\AssetBasket;

class BorrowCartController extends Controller
{
    /**
     * Menampilkan halaman Keranjang Peminjaman.
     */
    public function index(Request $request)
    {
        $cartItems = AssetBasket::with(['barang.subcategory.category', 'barang.brand'])
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
                    'isPreorder' => false,
                    'imageUrl' => $item->barang->image_url ? '/storage/' . $item->barang->image_url : null,
                ];
            });

        // Try to get default dates from the first item, if any
        $firstItem = AssetBasket::where('user_id', $request->user()->id)->first();
        $defaultStartDate = $firstItem && $firstItem->start_date ? $firstItem->start_date->format('Y-m-d') : '';
        $defaultStartTime = $firstItem && $firstItem->start_date ? $firstItem->start_date->format('H:i') : '';
        $defaultEndDate = $firstItem && $firstItem->end_date ? $firstItem->end_date->format('Y-m-d') : '';
        $defaultEndTime = $firstItem && $firstItem->end_date ? $firstItem->end_date->format('H:i') : '';

        return Inertia::render('Smart/User/BorrowCart', [
            'cartItems' => $cartItems,
            'defaultStartDate' => $defaultStartDate,
            'defaultStartTime' => $defaultStartTime,
            'defaultEndDate' => $defaultEndDate,
            'defaultEndTime' => $defaultEndTime,
        ]);
    }

    /**
     * Memperbarui kuantitas atau tanggal pada item keranjang peminjaman.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date',
        ]);

        $item = AssetBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->update($validated);

        return redirect()->back()->with('success', 'Keranjang peminjaman diperbarui.');
    }

    /**
     * Menghapus item dari keranjang peminjaman.
     */
    public function destroy(Request $request, $id)
    {
        $item = AssetBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->delete();

        return redirect()->back()->with('success', 'Barang dihapus dari keranjang.');
    }
}
