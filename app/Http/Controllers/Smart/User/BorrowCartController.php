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
        $cartItems = AssetBasket::with([
            'barang.subcategory.category',
            'barang.brand',
            'subcategory.category',
            'subcategory.barangs',
        ])
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
                    'category_name' => $item->barang 
                        ? ($item->barang->subcategory->category->name ?? '-') 
                        : ($item->subcategory->category->name ?? '-'),
                    'subcategory_name' => $item->barang 
                        ? ($item->barang->subcategory->name ?? '-') 
                        : ($item->subcategory->name ?? '-'),
                    'code' => $item->barang?->number ?? '-',
                    'stock' => $stock,
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'isPreorder' => false,
                    'imageUrl' => $item->barang_id
                        ? ($item->barang?->image_url ? '/storage/' . $item->barang->image_url : null)
                        : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/storage/' . $firstBarang->image_url : null),
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
     * Menambahkan barang ke dalam keranjang peminjaman.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'barang_id' => 'nullable|exists:barangs,id',
            'quantity' => 'required|integer|min:1|max:999999',
        ]);

        $userId = $request->user()->id;

        // Find or create in asset basket
        if (empty($validated['barang_id'])) {
            $basketItem = AssetBasket::firstOrNew([
                'user_id' => $userId,
                'subcategory_id' => $validated['subcategory_id'],
                'barang_id' => null,
            ]);
        } else {
            $basketItem = AssetBasket::firstOrNew([
                'user_id' => $userId,
                'subcategory_id' => $validated['subcategory_id'],
                'barang_id' => $validated['barang_id'],
            ]);
        }
        $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];

        // Assets need default start and end dates if not set, or we set them to tomorrow and the day after
        if (!$basketItem->start_date) {
            $basketItem->start_date = now()->addDay();
        }
        if (!$basketItem->end_date) {
            $basketItem->end_date = now()->addDays(2);
        }

        $basketItem->save();

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang!');
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
