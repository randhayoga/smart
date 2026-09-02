<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Cart\AssetBasket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Borrow Cart Controller managing shopping basket operations for loanable asset items.
 */
class BorrowCartController extends Controller
{
    /**
     * Display the asset borrow cart (Keranjang Peminjaman).
     */
    public function index(Request $request): Response
    {
        $cartItems = AssetBasket::with([
            'barang.subcategory.category',
            'barang.brand',
            'barang.uom',
            'subcategory.category',
            'subcategory.barangs.uom',
        ])
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($item) {
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
                    'stock' => 0, // Stock calculation deprecated; loans can be placed regardless of stock
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'isPreorder' => false,
                    'uom' => $item->barang?->uom?->name ?? ($item->subcategory?->barangs?->first()?->uom?->name ?? 'satuan'),
                    'imageUrl' => $item->barang_id
                        ? ($item->barang?->image_url ? '/media/' . $item->barang->image_url : null)
                        : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null),
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
     * Add an item to the borrow cart.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subcategory_id' => 'required_without:barang_id|nullable|exists:subcategories,id',
            'barang_id' => 'required_without:subcategory_id|nullable|exists:barangs,id',
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

        // Assets need default start and end dates if not set
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
     * Update item quantity or borrowing dates in the borrow cart.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:999999',
            'start_date' => 'sometimes|nullable|date|after_or_equal:today',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
        ], [
            'start_date.after_or_equal' => 'Tanggal mulai peminjaman tidak boleh di masa lalu.',
            'end_date.after_or_equal' => 'Tanggal selesai peminjaman harus sama dengan atau setelah tanggal mulai peminjaman.',
        ]);

        $item = AssetBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->update($validated);

        return redirect()->back()->with('success', 'Keranjang peminjaman diperbarui.');
    }

    /**
     * Remove an item from the borrow cart.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $item = AssetBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->delete();

        return redirect()->back()->with('success', 'Barang dihapus dari keranjang.');
    }
}
