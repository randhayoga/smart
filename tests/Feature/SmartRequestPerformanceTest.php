<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmartRequestPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): AdmUser
    {
        $employee = HrdEmployee::factory()->create(['employee_id' => '252525']);
        return AdmUser::factory()->create(['employee_id' => $employee->employee_id]);
    }

    private function createManager(): AdmUser
    {
        $managerUser = AdmUser::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);
        return $managerUser;
    }

    public function test_admin_inbox_does_not_suffer_from_n_plus_one_stock_queries(): void
    {
        $admin = $this->createAdmin();
        $requester = AdmUser::factory()->create();

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        // Create 5 different requests, each with 3 items (total 15 items)
        for ($i = 1; $i <= 5; $i++) {
            $req = SmartRequest::create([
                'request_number' => "REQ-P{$i}",
                'user_id' => $requester->id,
                'approver_id' => $admin->id,
                'utilization' => 'corporate',
                'reasoning' => 'Performance test',
                'status' => 'approve',
            ]);

            for ($j = 1; $j <= 3; $j++) {
                $barang = Barang::factory()->create([
                    'subcategory_id' => $sub->id,
                    'brand_id' => $brand->id,
                    'uom_id' => $uom->id,
                ]);
                $lot = Lot::factory()->create(['barang_id' => $barang->id]);
                Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia']);

                RequestItem::create([
                    'request_id' => $req->id,
                    'barang_id' => $barang->id,
                    'subcategory_id' => $sub->id,
                    'quantity_requested' => 1,
                ]);
            }
        }

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('smart.inbox'));
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Total queries for whole HTTP request including session, user roles, notifications,
        // eager-loaded relationships and 2 batched stock queries (instead of 30+ N+1 queries).
        $this->assertLessThan(32, count($queries), 'Admin inbox query count exceeded batching threshold.');
    }

    public function test_manager_approved_requests_loads_cleanly_with_dates(): void
    {
        $manager = $this->createManager();
        $requester = AdmUser::factory()->create();

        $req = SmartRequest::create([
            'request_number' => 'REQ-APP01',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'reasoning' => 'Approved request test',
            'status' => 'approve',
        ]);

        RequestItem::create([
            'request_id' => $req->id,
            'quantity_requested' => 2,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(5),
        ]);

        $response = $this->actingAs($manager)->get(route('smart.approved'));
        $response->assertStatus(200);
        $response->assertInertia(fn($page) => 
            $page->component('Smart/Manager/SudahApprove')
                ->has('requests', 1)
        );
    }
}
