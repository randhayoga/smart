<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Master\Category;
use App\Models\Master\Type;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse Controller rendering the searchable item catalog and category filters for employees.
 */
class BrowseController extends Controller
{
    /**
     * Display the item catalog search page (Browse).
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        
        $items = Type::with(['category', 'models.brand', 'models.uom'])
            ->get()
            ->map(function ($type) {
                $firstModel = $type->models->first();

                return [
                    'id' => $type->id,
                    'type_id' => $type->id,
                    'subcategory_id' => $type->id,
                    'model_id' => $firstModel ? $firstModel->id : null,
                    'barang_id' => $firstModel ? $firstModel->id : null,
                    'number' => $firstModel ? $firstModel->number : '-',
                    'code' => $firstModel ? $firstModel->number : '-',
                    'category' => $type->category->name ?? '-',
                    'category_id' => $type->category_id,
                    'category_name' => $type->category->name ?? '-',
                    'type_name' => $type->name,
                    'subcategory_name' => $type->name,
                    'is_consumable' => (bool) ($type->is_consumable ?? true),
                    'brand' => $firstModel && $firstModel->brand ? $firstModel->brand->name : '-',
                    'name' => $type->name,
                    'spec' => $firstModel ? $firstModel->specification : '-',
                    'stock' => 0, // Stock calculation deprecated; items can be requested regardless of stock
                    'imageUrl' => $firstModel && $firstModel->image_url ? '/media/' . $firstModel->image_url : null,
                    'uom' => $firstModel && $firstModel->uom ? $firstModel->uom->name : 'satuan',
                    'models' => $type->models->map(function ($model) {
                        return [
                            'id' => $model->id,
                            'number' => $model->number,
                            'name' => $model->name,
                            'brand' => $model->brand ? $model->brand->name : '-',
                            'specification' => $model->specification,
                            'imageUrl' => $model->image_url ? '/media/' . $model->image_url : null,
                            'uom' => $model->uom ? $model->uom->name : 'satuan',
                        ];
                    }),
                    'barangs' => $type->models->map(function ($model) {
                        return [
                            'id' => $model->id,
                            'number' => $model->number,
                            'name' => $model->name,
                            'brand' => $model->brand ? $model->brand->name : '-',
                            'specification' => $model->specification,
                            'imageUrl' => $model->image_url ? '/media/' . $model->image_url : null,
                            'uom' => $model->uom ? $model->uom->name : 'satuan',
                        ];
                    }),
                ];
            });

        return Inertia::render('Smart/User/Browse', [
            'user' => $request->user(),
            'items' => $items,
            'categories' => $categories,
        ]);
    }
}
