<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Cart\AssetBasket;
use App\Models\Inventory\Model as InventoryModel;
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
            'model.type.category',
            'model.brand',
            'model.uom',
            'type.category',
            'type.models.uom',
        ])
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'model_id' => $item->model_id,
                    'barang_id' => $item->model_id,
                    'type_id' => $item->type_id,
                    'subcategory_id' => $item->type_id,
                    'brand' => $item->model?->brand->name ?? '-',
                    'name' => $item->model?->name ?? 'Tidak Spesifik',
                    'spec' => $item->model?->specification ?? '',
                    'category' => $item->model 
                        ? ($item->model->type->category->name ?? '-') . ' (' . ($item->model->type->name ?? '-') . ')'
                        : ($item->type->category->name ?? '-') . ' (' . ($item->type->name ?? '-') . ')',
                    'category_name' => $item->model 
                        ? ($item->model->type->category->name ?? '-') 
                        : ($item->type->category->name ?? '-'),
                    'type_name' => $item->model 
                        ? ($item->model->type->name ?? '-') 
                        : ($item->type->name ?? '-'),
                    'subcategory_name' => $item->model 
                        ? ($item->model->type->name ?? '-') 
                        : ($item->type->name ?? '-'),
                    'number' => $item->model?->number ?? '-',
                    'code' => $item->model?->number ?? '-',
                    'stock' => 0, // Stock calculation deprecated; loans can be placed regardless of stock
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'isPreorder' => false,
                    'uom' => $item->model?->uom?->name ?? ($item->type?->models?->first()?->uom?->name ?? 'satuan'),
                    'imageUrl' => $item->model_id
                        ? ($item->model?->image_url ? '/media/' . $item->model->image_url : null)
                        : (($firstModel = $item->type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null),
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
        $typeId = $request->input('type_id', $request->input('subcategory_id'));
        $modelId = $request->input('model_id', $request->input('barang_id'));

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:999999',
            'type_id' => 'nullable|exists:types,id',
            'subcategory_id' => 'nullable|exists:types,id',
            'model_id' => 'nullable|exists:models,id',
            'barang_id' => 'nullable|exists:models,id',
        ]);

        if (empty($typeId) && empty($modelId)) {
            return redirect()->back()->withErrors(['type_id' => 'Type or Model is required.']);
        }

        if (empty($typeId) && !empty($modelId)) {
            $typeId = InventoryModel::find($modelId)?->type_id;
        }

        $userId = $request->user()->id;

        // Find or create in asset basket
        $basketItem = AssetBasket::firstOrNew([
            'user_id' => $userId,
            'type_id' => $typeId,
            'model_id' => $modelId ?: null,
        ]);
        $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];

        // Assets need default start and end dates if not set
        if (!$basketItem->start_date) {
            $basketItem->start_date = now()->addDay();
        }
        if (!$basketItem->end_date) {
            $basketItem->end_date = now()->addDays(2);
        }

        $basketItem->save();

        return redirect()->back()->with('success', __('requests.cart_item_added'));
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

        return redirect()->back()->with('success', __('requests.borrow_cart_updated'));
    }

    /**
     * Remove an item from the borrow cart.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $item = AssetBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->delete();

        return redirect()->back()->with('success', __('requests.cart_item_removed'));
    }
}
