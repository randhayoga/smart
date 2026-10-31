<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Type;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_asset_detail_by_id_and_code(): void
    {
        $user = User::factory()->create();

        $organizer = Organizer::firstOrCreate(['name' => 'IT Dept'], ['code' => 'IT']);
        $category = Category::firstOrCreate(['name' => 'Computer'], ['code' => 'COMP']);
        $type = Type::firstOrCreate(['name' => 'Laptop', 'category_id' => $category->id], ['code' => 'LP']);
        $brand = Brand::firstOrCreate(['name' => 'ThinkPad']);
        $uom = Uom::firstOrCreate(['name' => 'Unit']);
        $location = Location::firstOrCreate(['name' => 'HQ Floor 2'], ['full_name' => 'HQ - Floor 2 - IT Room']);

        $model = InventoryModel::create([
            'number' => 'MDL-001',
            'name' => 'ThinkPad T14',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'is_consumable' => false,
        ]);

        $lot = Lot::create([
            'number' => 'LOT-2026-001',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'condition' => 'Baik',
            'classification' => 'Aset',
            'borrowing_type' => 'ST',
            'burden' => 'Corporate',
            'purchase_grant_date' => now(),
        ]);

        $asset = Asset::create([
            'number' => 'AST-00001-2026',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'ST',
            'burden' => 'Corporate',
        ]);

        // By ID
        $response = $this->actingAs($user)->getJson(route('smart.inventory.assets.show', $asset->id));
        $response->assertOk();
        $response->assertJson([
            'id' => $asset->id,
            'number' => 'AST-00001-2026',
            'condition' => 'Bagus',
            'status' => 'Tersedia',
            'model_name' => 'ThinkPad T14',
            'barang_brand' => 'ThinkPad',
        ]);

        // By Code
        $codeResponse = $this->actingAs($user)->getJson(route('smart.inventory.assets.by-code', 'AST-00001-2026'));
        $codeResponse->assertOk();
        $codeResponse->assertJson([
            'id' => $asset->id,
            'number' => 'AST-00001-2026',
        ]);
    }
}
