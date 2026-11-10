<?php

namespace Tests\Unit;

use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\User;
use App\Services\Inventory\ConsumableMovementQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumableMovementRankingTest extends TestCase
{
    use RefreshDatabase;

    private ConsumableMovementQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConsumableMovementQueryService::class);
    }

    public function test_ranking_returns_flattened_rows_with_correct_sorting_and_rowspans(): void
    {
        $user = User::factory()->create();
        $cat = Category::factory()->create(['name' => 'Alat Tulis Kantor', 'code' => 'ATK']);
        $type = Type::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Kertas HVS',
            'code' => 'HVS',
            'is_consumable' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'PaperOne']);
        $uom = Uom::factory()->create(['name' => 'Rim']);

        $model = InventoryModel::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'HVS A4 80gr',
            'specification' => '80 GSM 500 Sheets',
            'number' => 'ATK-HVS-0001',
        ]);

        $req1 = SmartRequest::create([
            'request_number' => '022026-0001',
            'user_id' => $user->id,
            'approver_id' => $user->id,
            'utilization' => 'corporate',
            'org_id' => 93,
            'status' => 'success',
        ]);

        $req2 = SmartRequest::create([
            'request_number' => '022026-0002',
            'user_id' => $user->id,
            'approver_id' => $user->id,
            'utilization' => 'project',
            'project_id' => 11470,
            'status' => 'success',
        ]);

        // Destination 1: Corporate (larger stock out: 50)
        InventoryLog::create([
            'model_id' => $model->id,
            'user_id' => $user->id,
            'request_id' => $req1->id,
            'action_type' => 'stock_out',
            'quantity_change' => -50,
            'created_at' => Carbon::create(2026, 2, 10, 10, 0, 0),
        ]);

        // Destination 2: Project (smaller stock out: 20)
        InventoryLog::create([
            'model_id' => $model->id,
            'user_id' => $user->id,
            'request_id' => $req2->id,
            'action_type' => 'stock_out',
            'quantity_change' => -20,
            'created_at' => Carbon::create(2026, 2, 12, 11, 0, 0),
        ]);

        $metrics = $this->service->getMovementMetrics(['year' => '2026']);
        $ranking = $metrics['ranking'];

        $this->assertIsArray($ranking);
        $this->assertCount(2, $ranking);

        // Row 0: Category span 2, Type span 2, Model span 2, largest stock out first (50)
        $row0 = $ranking[0];
        $this->assertEquals('Alat Tulis Kantor', $row0['category_name']);
        $this->assertEquals(2, $row0['category_row_span']);
        $this->assertEquals('Kertas HVS', $row0['type_name']);
        $this->assertEquals(2, $row0['type_row_span']);
        $this->assertEquals('PaperOne HVS A4 80gr 80 GSM 500 Sheets', $row0['model_name']);
        $this->assertEquals(2, $row0['model_row_span']);
        $this->assertEquals(1, $row0['request_count']);
        $this->assertEquals(50, $row0['stock_out_qty']);

        // Row 1: Merged spans should be 0, smaller stock out (20)
        $row1 = $ranking[1];
        $this->assertEquals('Alat Tulis Kantor', $row1['category_name']);
        $this->assertEquals(0, $row1['category_row_span']);
        $this->assertEquals('Kertas HVS', $row1['type_name']);
        $this->assertEquals(0, $row1['type_row_span']);
        $this->assertEquals('PaperOne HVS A4 80gr 80 GSM 500 Sheets', $row1['model_name']);
        $this->assertEquals(0, $row1['model_row_span']);
        $this->assertEquals(1, $row1['request_count']);
        $this->assertEquals(20, $row1['stock_out_qty']);
    }

    public function test_ranking_returns_empty_when_no_matching_stock_out(): void
    {
        $metrics = $this->service->getMovementMetrics(['year' => '2099']);
        $ranking = $metrics['ranking'];

        $this->assertIsArray($ranking);
        $this->assertEmpty($ranking);
    }
}
