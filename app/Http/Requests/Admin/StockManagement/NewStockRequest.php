<?php

namespace App\Http\Requests\Admin\StockManagement;

use App\Models\Master\Category;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Type;
use App\Models\Inventory\Lot;
use App\Models\Master\Brand;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request validating dynamic unified stock creation (Model, LOT, Asset).
 */
class NewStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', Rule::exists(Category::class, 'id')],
            'type_id' => ['required', Rule::exists(Type::class, 'id')],
            'brand_id' => ['required', Rule::exists(Brand::class, 'id')],
            'create_new_type' => ['required', 'boolean'],
        ];

        $category = Category::find($this->input('category_id'));
        $type = Type::find($this->input('type_id'));
        $isComp = $category && ($category->code === 'COMP' || strtolower($category->name) === 'computer');
        $isKend = $category && ($category->code === 'KEND');
        $isConsumable = $type ? (bool) $type->is_consumable : false;

        // Model validation
        if (!$this->boolean('create_new_type')) {
            $rules['model_id'] = ['required', Rule::exists(InventoryModel::class, 'id')];
        } else {
            $rules['model_name'] = ['required', 'string', 'max:255'];
            $rules['uom_id'] = ['required', Rule::exists(Uom::class, 'id')];
            $rules['model_number'] = ['nullable', 'string', 'max:50', 'unique:models,number'];

            if ($isComp) {
                $rules['model_specification'] = ['nullable', 'string', 'max:255'];
            } else {
                $rules['model_specification'] = ['required', 'string', 'max:1000'];
            }

            if ($isConsumable) {
                $rules['min_stock_threshold'] = ['required', 'integer', 'min:0'];
            } else {
                $rules['min_stock_threshold'] = ['nullable', 'integer', 'min:0'];
            }
        }

        // LOT validation
        $rules['input_lot'] = ['required', 'boolean'];
        if ($this->boolean('input_lot')) {
            $rules['create_new_lot'] = ['required', 'boolean'];

            if (!$this->boolean('create_new_lot')) {
                $rules['lot_id'] = ['required', Rule::exists(Lot::class, 'id')];
            } else {
                $rules['entry_type'] = ['required', Rule::in(['Procurement', 'Hibah'])];
                $rules['organizer_id'] = ['required', Rule::exists(Organizer::class, 'id')];
                $rules['burden'] = ['required', Rule::in(['Corporate', 'Project'])];
                $rules['project_id'] = [
                    'required_if:burden,Project',
                    'nullable',
                    Rule::exists(TbProject::class, 'id_project'),
                ];

                if ($this->input('entry_type') === 'Procurement') {
                    $rules['po_number'] = ['required', 'string', 'max:255'];
                    $rules['purchase_date'] = ['required', 'date'];
                    $rules['vendor_id'] = ['required', Rule::exists(Vendor::class, 'id')];
                } elseif ($this->input('entry_type') === 'Hibah') {
                    $rules['memo_number'] = ['required', 'string', 'max:255'];
                    $rules['date_of_grant'] = ['required', 'date'];
                    $rules['memo_file'] = ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:2048'];
                    $rules['vendor_id'] = ['nullable', Rule::exists(Vendor::class, 'id')];
                }

                if ($isConsumable) {
                    $rules['initial_quantity'] = ['required', 'integer', 'min:0', 'max:2147483647'];
                    $rules['location_id'] = ['required', Rule::exists(Location::class, 'id')];
                    $rules['unit_price'] = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];
                    $rules['lot_photo'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:1024'];
                    $rules['use_parent_image'] = ['nullable', 'boolean'];
                }
            }

            // Asset validation (visible only when input_lot is true and category/type is not consumable)
            $rules['input_asset'] = ['required', 'boolean'];
            if ($this->boolean('input_asset')) {
                $rules['borrowing_type'] = ['required', Rule::in(['LT', 'ST'])];
                $rules['condition'] = ['required', Rule::in(['Bagus', 'Rusak', 'Good', 'Damaged'])];
                $rules['location_id'] = ['required', Rule::exists(Location::class, 'id')];
                $rules['price'] = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];
                $rules['classification'] = ['required', Rule::in(['Aset', 'Inventaris'])];
                $rules['photo'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:1024'];

                if ($isComp) {
                    $rules['asset_specification'] = ['required', 'string', 'max:1000'];
                } else {
                    $rules['asset_specification'] = ['nullable', 'string', 'max:1000'];
                }

                if ($isKend) {
                    $rules['plate_number'] = ['required', 'string', 'max:20'];
                } else {
                    $rules['plate_number'] = ['nullable', 'string', 'max:20'];
                }

                $rules['is_bulk'] = ['nullable', 'boolean'];
                if (!$isKend && $this->boolean('is_bulk')) {
                    $rules['bulk_quantity'] = ['required', 'integer', 'min:1', 'max:999'];
                } else {
                    $rules['bulk_quantity'] = ['nullable', 'integer', 'min:1', 'max:999'];
                }
            }
        }

        return $rules;
    }
}
