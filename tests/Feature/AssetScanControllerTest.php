<?php

namespace Tests\Feature;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model;
use App\Models\Master\Type;
use App\Models\Master\Category;
use App\Models\Master\Brand;
use App\Models\Master\Organizer;
use App\Models\Master\Location;
use App\Models\TbProject;
use App\Models\User;
use App\Models\HrdEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetScanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_page_provides_qc_burden_and_projects(): void
    {
        HrdEmployee::factory()->create(['employee_id' => '255578']);
        /** @var User $user */
        $user = User::factory()->create(['employee_id' => '255578']);

        $category = Category::factory()->create(['name' => 'Elektronik', 'code' => 'ELK']);
        $type = Type::factory()->create(['category_id' => $category->id, 'name' => 'Laptop']);
        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $model = Model::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'name' => 'ThinkPad T14',
            'specification' => 'Core i7 16GB 512GB',
        ]);
        $organizer = Organizer::factory()->create(['name' => 'IT Dept']);
        $lot = Lot::factory()->create([
            'barang_id' => $model->id,
            'organizer_id' => $organizer->id,
        ]);
        $location = Location::factory()->create(['name' => 'Gudang Utama']);
        $project = TbProject::create([
            'no_project' => 'PRJ-2026-001',
            'project_name' => 'Project Alpha',
        ]);

        $asset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'AST-2026-0001',
            'condition' => 'Bagus',
            'status' => 'Tersedia',
            'qc_pass' => true,
            'last_qc' => now(),
            'burden' => 'Project',
            'project_id' => $project->id_project,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
        ]);

        $cleanNumber = preg_replace('/[^a-zA-Z0-9]/', '', (string)$asset->number);

        $response = $this->actingAs($user)->get(route('smart.scan', ['asset' => $cleanNumber]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/MultiRoles/ScanResult')
            ->has('asset')
            ->where('asset.number', 'AST-2026-0001')
            ->where('asset.qc_pass', true)
            ->where('asset.burden', 'Project')
            ->where('asset.project_id', $project->id_project)
            ->where('asset.project_no', 'PRJ-2026-001')
            ->where('asset.project_name', 'Project Alpha')
            ->has('projects')
            ->has('locations')
        );
    }
}
