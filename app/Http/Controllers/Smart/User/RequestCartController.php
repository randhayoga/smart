<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Cart\ConsumableBasket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Request Cart Controller managing shopping basket operations for consumable inventory items.
 */
class RequestCartController extends Controller
{
    /**
     * Display the consumable items shopping cart (Keranjang Habis Pakai).
     */
    public function index(Request $request): Response
    {
        $cartItems = ConsumableBasket::with([
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
                    'stock' => 0, // Stock calculation deprecated; requests can be placed regardless of stock
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'uom' => $item->barang?->uom?->name ?? ($item->subcategory?->barangs?->first()?->uom?->name ?? 'satuan'),
                    'imageUrl' => $item->barang_id
                        ? ($item->barang?->image_url ? '/media/' . $item->barang->image_url : null)
                        : (($firstBarang = $item->subcategory?->barangs?->first()) && $firstBarang->image_url ? '/media/' . $firstBarang->image_url : null),
                ];
            });

        return Inertia::render('Smart/User/RequestCart', [
            'cartItems' => $cartItems,
        ]);
    }

    /**
     * Add an item to the consumable cart.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subcategory_id' => 'required_without:barang_id|nullable|exists:subcategories,id',
            'barang_id' => 'required_without:subcategory_id|nullable|exists:barangs,id',
            'quantity' => 'required|integer|min:1|max:999999',
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
                'subcategory_id' => $validated['subcategory_id'],
                'barang_id' => $validated['barang_id'],
            ]);
        }
        $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
        $basketItem->save();

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang!');
    }

    /**
     * Update item quantity in the consumable cart.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:999999',
        ]);

        $item = ConsumableBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->update(['quantity' => $validated['quantity']]);

        return redirect()->back()->with('success', 'Jumlah barang diperbarui.');
    }

    /**
     * Remove an item from the consumable cart.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $item = ConsumableBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->delete();

        return redirect()->back()->with('success', 'Barang dihapus dari keranjang.');
    }
}
