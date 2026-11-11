<?php

namespace Tests\Feature;

use App\Models\Inventory\Asset;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\User;
use Database\Seeders\DummyRequestShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DummyRequestShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_showcase_seeder_allocates_distinct_assets_to_each_request(): void
    {
        // 1. Create required users
        $corpUser = User::factory()->create(['employee_id' => '265656', 'employee_name' => 'Corp User']);
        $projUser = User::factory()->create(['employee_id' => '184697', 'employee_name' => 'Proj User']);
        $adminUser = User::factory()->create(['employee_id' => '255578', 'employee_name' => 'Admin User']);
        $ifsDm = User::factory()->create(['employee_id' => '033340', 'employee_name' => 'IFS DM']);
        $projPm = User::factory()->create(['employee_id' => '023061', 'employee_name' => 'Proj PM']);

        // 2. Create sample LT and ST assets
        $firstLt = Asset::factory()->create([
            'number' => '01621-ACC-AM-CFS-PTRE-26',
            'borrowing_type' => 'LT',
            'status' => 'Tersedia',
            'approval_status' => null,
        ]);

        for ($i = 2; $i <= 4; $i++) {
            Asset::factory()->create([
                'number' => "AST-LT-{$i}",
                'borrowing_type' => 'LT',
                'status' => 'Tersedia',
                'approval_status' => null,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Asset::factory()->create([
                'number' => "AST-ST-{$i}",
                'borrowing_type' => 'ST',
                'status' => 'Tersedia',
                'approval_status' => null,
            ]);
        }

        // 3. Create consumable items needed for Scenario 5 (including 112026-0004)
        $category = \App\Models\Master\Category::firstOrCreate(['code' => 'ATK'], ['name' => 'Alat Tulis Kantor']);
        $type = \App\Models\Master\Type::firstOrCreate(['code' => 'ATK-GEN', 'category_id' => $category->id], ['name' => 'General ATK', 'is_consumable' => true]);
        $brand = \App\Models\Master\Brand::firstOrCreate(['name' => 'Standard']);
        $uom = \App\Models\Master\Uom::firstOrCreate(['name' => 'Rim']);
        $organizer = \App\Models\Master\Organizer::firstOrCreate(['name' => 'CFS']);

        $hvs = \App\Models\Inventory\Model::create([
            'number' => 'ATK-HVS4-0001',
            'name' => 'Kertas HVS A4',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);
        $pen = \App\Models\Inventory\Model::create([
            'number' => 'ATK-PLPH-0001',
            'name' => 'Pulpen Gel',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        \App\Models\Inventory\Lot::create([
            'number' => 'LOT-HVS-001',
            'model_id' => $hvs->id,
            'organizer_id' => $organizer->id,
            'purchase_grant_date' => now(),
            'current_quantity' => 100,
            'initial_quantity' => 100,
        ]);
        \App\Models\Inventory\Lot::create([
            'number' => 'LOT-PEN-001',
            'model_id' => $pen->id,
            'organizer_id' => $organizer->id,
            'purchase_grant_date' => now(),
            'current_quantity' => 100,
            'initial_quantity' => 100,
        ]);

        $this->seed(DummyRequestShowcaseSeeder::class);

        // 4. Verify all asset requests
        $targetRequestNumbers = [
            '102026-0001', // Corp LT Assignment (should use firstLt: 01621-ACC-AM-CFS-PTRE-26)
            '102026-0002', // Proj ST Borrowing (Returned)
            '102026-0003', // Corp ST Borrowing (Returned)
            '102026-0004', // Proj LT Assignment
            '112026-0002', // Proj ST Borrowing (Active)
            '112026-0004', // Corp ST Borrowing (Active, must NOT use firstLt!)
        ];

        $requests = SmartRequest::whereIn('request_number', $targetRequestNumbers)->get();
        $this->assertCount(6, $requests);

        $req102026_0001 = $requests->firstWhere('request_number', '102026-0001');
        $req112026_0004 = $requests->firstWhere('request_number', '112026-0004');

        $f1 = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $req102026_0001->id))->first();
        $f4 = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $req112026_0004->id))->first();

        $this->assertEquals($firstLt->id, $f1->asset_id, '102026-0001 should be assigned to first LT asset');
        $this->assertNotEquals($firstLt->id, $f4->asset_id, '112026-0004 must NOT reuse 01621-ACC-AM-CFS-PTRE-26');

        $allocatedAssetIds = [];
        foreach ($requests as $req) {
            $fulfillments = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $req->id))->get();
            $this->assertNotEmpty($fulfillments, "Request {$req->request_number} should have fulfillments");
            foreach ($fulfillments as $f) {
                $this->assertNotNull($f->asset_id);
                $this->assertNotContains(
                    $f->asset_id,
                    $allocatedAssetIds,
                    "Asset {$f->asset_id} is allocated to multiple requests simultaneously ({$req->request_number})"
                );
                $allocatedAssetIds[] = $f->asset_id;
            }
        }

        // Distinct count must equal total count of allocated assets
        $this->assertCount(6, $allocatedAssetIds);
        $this->assertCount(6, array_unique($allocatedAssetIds));

        // Verify that generic 'Perubahan status' logs are completely suppressed
        $perubahanStatusLogs = \App\Models\Inventory\AssetLifecycle::whereIn('asset_id', $allocatedAssetIds)
            ->where('action_type', 'Perubahan status')
            ->count();
        $this->assertEquals(0, $perubahanStatusLogs, 'Redundant "Perubahan status" logs must be suppressed');

        // Active request assets should have their latest open lifecycle as Assignment or Peminjaman
        $activeReqNumbers = ['102026-0001', '102026-0004', '112026-0002', '112026-0004'];
        foreach ($activeReqNumbers as $num) {
            $r = $requests->firstWhere('request_number', $num);
            $aid = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $r->id))->value('asset_id');
            $latestLifecycle = \App\Models\Inventory\AssetLifecycle::where('asset_id', $aid)->latest('id')->first();
            $this->assertNotNull($latestLifecycle);
            $this->assertContains($latestLifecycle->action_type, ['Assignment', 'Peminjaman']);
            $this->assertNull($latestLifecycle->end_date, "Active request {$num} latest lifecycle should have null end_date");
        }

        // Returned request assets should have their latest open lifecycle as Pengembalian
        $returnedReqNumbers = ['102026-0002', '102026-0003'];
        foreach ($returnedReqNumbers as $num) {
            $r = $requests->firstWhere('request_number', $num);
            $aid = RequestFulfillment::whereHas('requestItem', fn ($q) => $q->where('request_id', $r->id))->value('asset_id');
            $latestLifecycle = \App\Models\Inventory\AssetLifecycle::where('asset_id', $aid)->latest('id')->first();
            $this->assertNotNull($latestLifecycle);
            $this->assertEquals('Pengembalian', $latestLifecycle->action_type);
            $this->assertEquals('Tersedia', $latestLifecycle->status);
            $this->assertNull($latestLifecycle->end_date, "Returned request {$num} latest lifecycle should have null end_date");
        }
    }

    public function test_request_showcase_seeder_is_idempotent(): void
    {
        User::factory()->create(['employee_id' => '265656']);
        User::factory()->create(['employee_id' => '184697']);
        User::factory()->create(['employee_id' => '255578']);
        User::factory()->create(['employee_id' => '033340']);
        User::factory()->create(['employee_id' => '023061']);

        for ($i = 1; $i <= 6; $i++) {
            Asset::factory()->create([
                'borrowing_type' => 'LT',
                'status' => 'Tersedia',
                'approval_status' => null,
            ]);
        }

        $this->seed(DummyRequestShowcaseSeeder::class);
        $firstCount = SmartRequest::whereIn('request_number', DummyRequestShowcaseSeeder::SHOWCASE_REQ_NUMBERS)->count();

        // Run second time
        $this->seed(DummyRequestShowcaseSeeder::class);
        $secondCount = SmartRequest::whereIn('request_number', DummyRequestShowcaseSeeder::SHOWCASE_REQ_NUMBERS)->count();

        $this->assertEquals($firstCount, $secondCount);
    }
}
