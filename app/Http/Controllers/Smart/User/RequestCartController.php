<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Cart\ConsumableBasket;
use App\Models\Inventory\Model as InventoryModel;
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
                    'stock' => 0, // Stock calculation deprecated; requests can be placed regardless of stock
                    'quantity' => $item->quantity,
                    'selected' => false,
                    'uom' => $item->model?->uom?->name ?? ($item->type?->models?->first()?->uom?->name ?? 'satuan'),
                    'imageUrl' => $item->model_id
                        ? ($item->model?->image_url ? '/media/' . $item->model->image_url : null)
                        : (($firstModel = $item->type?->models?->first()) && $firstModel->image_url ? '/media/' . $firstModel->image_url : null),
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

        // Find or create in consumable basket
        $basketItem = ConsumableBasket::firstOrNew([
            'user_id' => $userId,
            'type_id' => $typeId,
            'model_id' => $modelId ?: null,
        ]);
        $basketItem->quantity = ($basketItem->quantity ?? 0) + $validated['quantity'];
        $basketItem->save();

        return redirect()->back()->with('success', __('requests.cart_item_added'));
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

        return redirect()->back()->with('success', __('requests.cart_quantity_updated'));
    }

    /**
     * Remove an item from the consumable cart.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $item = ConsumableBasket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $item->delete();

        return redirect()->back()->with('success', __('requests.cart_item_removed'));
    }
}
