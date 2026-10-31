<?php

namespace Tests\Feature\Admin\ManualRequest;

use App\Models\HrdOrgchart;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $borrower;
    private User $dm;
    private HrdOrgchart $orgchart;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->dm = User::factory()->create(['employee_id' => 'DM-01', 'active' => 1]);
        $this->orgchart = HrdOrgchart::create([
            'org_code' => 'ORG-CIT20',
            'org_name' => 'Corporate IT',
            'employee_id' => $this->dm->id,
        ]);
        $this->admin = User::factory()->create(['employee_id' => 'ADM-01', 'orgchart_id' => $this->orgchart->id, 'active' => 1]);
        $this->borrower = User::factory()->create(['employee_id' => 'USR-01', 'orgchart_id' => $this->orgchart->id, 'active' => 1]);
    }

    public function test_requires_form_file_upload_on_submission(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('smart.requests.manual.store'), [
                'request_type' => 'consumable',
                'user_id' => $this->borrower->id,
                'approver_id' => $this->dm->id,
                'utilization' => 'corporate',
                'org_id' => $this->orgchart->id,
                'date' => '2026-10-27',
                'items' => [
                    ['model_id' => 1, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['form_file']);
    }

    public function test_stores_st_borrowing_and_lt_assignment_via_http_endpoint(): void
    {
        $category = Category::factory()->create(['code' => 'IT', 'name' => 'IT']);
        $brand = Brand::factory()->create(['name' => 'HP']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $organizer = Organizer::factory()->create(['name' => 'IT']);
        $location = Location::factory()->create(['name' => 'HQ']);

        $type = Type::factory()->create([
            'category_id' => $category->id,
            'code' => 'LAP',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
        ]);

        $stAsset = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'AST-HTTP-ST1',
            'status' => 'Tersedia',
            'approval_status' => null,
            'borrowing_type' => 'ST',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('smart.requests.manual.store'), [
                'request_type' => 'st',
                'user_id' => $this->borrower->id,
                'approver_id' => $this->dm->id,
                'utilization' => 'corporate',
                'org_id' => $this->orgchart->id,
                'reasoning' => 'Client demo',
                'start_date' => '2026-10-27',
                'end_date' => '2026-11-01',
                'form_file' => UploadedFile::fake()->create('form.pdf', 200, 'application/pdf'),
                'items' => [
                    [
                        'asset_id' => $stAsset->id,
                        'reasoning' => 'With charger',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assets', [
            'id' => $stAsset->id,
            'status' => 'Dipinjam (ST)',
        ]);
        $this->assertDatabaseHas('request_items', [
            'model_id' => $model->id,
            'reasoning' => 'With charger',
            'status' => 'fulfilled',
        ]);
    }
}
