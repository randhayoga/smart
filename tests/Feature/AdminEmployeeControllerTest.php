<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Subcategory;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestFulfillment;
use App\Models\Request\RequestHandover;
use App\Models\Request\RequestItem;
use App\Models\Request\RequestReturn;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Feature test for Admin Employee Borrowing Controller (Daftar Karyawan) and EmployeeLoanController.
 * Adheres strictly to Test Driven Development and Cruddy by Design principles.
 */
class AdminEmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        return User::factory()->create(['employee_id' => '252525']);
    }

    private function createDepartment(string $name = 'Information Technology'): HrdOrgchart
    {
        $uniqueName = $name . ' ' . rand(1000, 9999);
        return HrdOrgchart::create([
            'org_name' => $uniqueName,
            'org_code' => 'IT-' . rand(100, 999),
        ]);
    }

    private function createEmployee(string $name, string $employeeId, ?HrdOrgchart $org = null): User
    {
        $org = $org ?? $this->createDepartment();
        return User::create([
            'orgchart_id' => $org->id,
            'employee_id' => $employeeId,
            'employee_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . rand(10, 99) . '@example.com',
            'active' => true,
        ]);
    }

    private function createRequest(User $user, string $status = 'borrow'): SmartRequest
    {
        $admin = User::where('employee_id', '252525')->first() ?? $this->createAdmin();
        $reqNum = 'REQ-' . rand(1000, 9999);
        return SmartRequest::create([
            'request_number' => $reqNum,
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'utilization' => 'corporate',
            'reasoning' => 'Test borrow reasoning',
            'status' => $status,
        ]);
    }

    private function createRequestItem(SmartRequest $request, ?Carbon $startDate = null, ?Carbon $endDate = null): RequestItem
    {
        return RequestItem::create([
            'request_id' => $request->id,
            'quantity_requested' => 1,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'fulfilled',
        ]);
    }

    private function createUnit(string $number, string $barangName = 'ThinkPad X1 Carbon', string $brandName = 'Lenovo'): Unit
    {
        $location = Location::firstOrCreate(
            ['name' => 'Ruang Server'],
            ['full_name' => 'Gedung Pusat - Lantai 2 - Ruang Server']
        );
        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $brand = Brand::firstOrCreate(['name' => $brandName]);
        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'name' => $barangName,
        ]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id, 'location_id' => $location->id]);

        return Unit::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Dipinjam',
            'condition' => 'Bagus',
            'number' => $number,
        ]);
    }

    public function test_admin_can_view_daftar_karyawan_page(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment('Human Resources');
        $employee = $this->createEmployee('Budi Santoso', '112233', $dept);

        $response = $this->actingAs($admin)->get(route('smart.karyawan.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/Karyawan/DaftarKaryawan')
            ->has('employees')
            ->has('departments')
            ->where('employees.0.employee_id', fn ($val) => !empty($val))
        );
    }

    public function test_non_admin_cannot_access_daftar_karyawan(): void
    {
        $user = $this->createEmployee('Regular User', '999999');

        $response = $this->actingAs($user)->get(route('smart.karyawan.index'));

        $response->assertStatus(403);
    }

    public function test_active_assets_count_calculates_correctly(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment('Finance');
        $userActive = $this->createEmployee('Active Borrower', '888001', $dept);
        $userHistorical = $this->createEmployee('Historical Borrower', '888002', $dept);
        $userNatural = $this->createEmployee('Natural Borrower', '888003', $dept);

        $unit1 = $this->createUnit('AST-ACT-001');
        $unit2 = $this->createUnit('AST-HIST-001');
        $unit3 = $this->createUnit('AST-NAT-001');

        // 1. Manual active loan for $userActive
        $req1 = $this->createRequest($userActive, 'borrow');
        $item1 = $this->createRequestItem($req1);
        RequestFulfillment::create([
            'request_item_id' => $item1->id,
            'unit_id' => $unit1->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => Carbon::yesterday(),
            'confirmed_at' => Carbon::yesterday(),
            'completed_at' => null,
        ]);

        // 2. Completed loan for $userHistorical
        $req2 = $this->createRequest($userHistorical, 'success');
        $item2 = $this->createRequestItem($req2);
        RequestFulfillment::create([
            'request_item_id' => $item2->id,
            'unit_id' => $unit2->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => Carbon::now()->subDays(5),
            'confirmed_at' => Carbon::now()->subDays(5),
            'completed_at' => Carbon::yesterday(),
        ]);

        // 3. Natural active loan for $userNatural (confirmed via handover)
        $req3 = $this->createRequest($userNatural, 'borrow');
        $handover = RequestHandover::create([
            'request_id' => $req3->id,
            'method' => 'pickup',
            'scheduled_date' => Carbon::yesterday(),
            'location' => 'Ruang IFS',
            'is_auto_set' => false,
            'user_confirmed_at' => Carbon::yesterday(),
        ]);
        $item3 = $this->createRequestItem($req3);
        RequestFulfillment::create([
            'request_item_id' => $item3->id,
            'unit_id' => $unit3->id,
            'handover_id' => $handover->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => Carbon::yesterday(),
            'confirmed_at' => null, // Left null in natural flow
            'completed_at' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('smart.karyawan.index'));
        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) use ($userActive, $userHistorical, $userNatural) {
            $employees = collect($page->toArray()['props']['employees']);

            $activeEmp = $employees->firstWhere('employee_id', $userActive->employee_id);
            $this->assertNotNull($activeEmp);
            $this->assertEquals(1, $activeEmp['active_assets_count']);

            $histEmp = $employees->firstWhere('employee_id', $userHistorical->employee_id);
            $this->assertNotNull($histEmp);
            $this->assertEquals(0, $histEmp['active_assets_count']);

            $natEmp = $employees->firstWhere('employee_id', $userNatural->employee_id);
            $this->assertNotNull($natEmp);
            $this->assertEquals(1, $natEmp['active_assets_count']);
        });
    }

    public function test_admin_can_fetch_employee_loans_json(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment('Operations');
        $employee = $this->createEmployee('Multi Loan User', '777001', $dept);

        $unitActive = $this->createUnit('AST-MULTI-01', 'MacBook Pro', 'Apple');
        $unitHist = $this->createUnit('AST-MULTI-02', 'Dell XPS 15', 'Dell');

        // Active loan with due date
        $req1 = $this->createRequest($employee, 'borrow');
        $item1 = $this->createRequestItem($req1, Carbon::now()->subDays(2), Carbon::now()->addDays(5));
        RequestFulfillment::create([
            'request_item_id' => $item1->id,
            'unit_id' => $unitActive->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => Carbon::now()->subDays(2),
            'confirmed_at' => Carbon::now()->subDays(2),
            'completed_at' => null,
        ]);

        // Historical loan
        $req2 = $this->createRequest($employee, 'success');
        $item2 = $this->createRequestItem($req2, Carbon::now()->subDays(10), Carbon::now()->subDays(2));
        RequestFulfillment::create([
            'request_item_id' => $item2->id,
            'unit_id' => $unitHist->id,
            'quantity_fulfilled' => 1,
            'assigned_at' => Carbon::now()->subDays(10),
            'confirmed_at' => Carbon::now()->subDays(10),
            'completed_at' => Carbon::yesterday(),
        ]);

        $hrdEmployee = HrdEmployee::where('employee_id', $employee->employee_id)->firstOrFail();
        $response = $this->actingAs($admin)->getJson(route('smart.karyawan.loans', $hrdEmployee));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'active' => [
                '*' => [
                    'id',
                    'unit_id',
                    'unit_number',
                    'barang_nama',
                    'brand',
                    'condition',
                    'location',
                    'start_date',
                    'due_date',
                    'start_date_raw',
                    'asset',
                ]
            ],
            'history' => [
                '*' => [
                    'id',
                    'unit_id',
                    'unit_number',
                    'barang_nama',
                    'brand',
                    'condition',
                    'location',
                    'start_date',
                    'return_date',
                    'return_date_raw',
                    'asset',
                ]
            ],
        ]);

        $data = $response->json();
        $this->assertCount(1, $data['active']);
        $this->assertEquals('AST-MULTI-01', $data['active'][0]['unit_number']);
        $this->assertEquals('MacBook Pro', $data['active'][0]['barang_nama']);
        $this->assertEquals('Apple', $data['active'][0]['brand']);
        $this->assertEquals('AST-MULTI-01', $data['active'][0]['asset']['number']);
        $this->assertEquals('Bagus', $data['active'][0]['asset']['condition']);

        $this->assertCount(1, $data['history']);
        $this->assertEquals('AST-MULTI-02', $data['history'][0]['unit_number']);
        $this->assertEquals('Dell XPS 15', $data['history'][0]['barang_nama']);
        $this->assertEquals('Dell', $data['history'][0]['brand']);
        $this->assertEquals('AST-MULTI-02', $data['history'][0]['asset']['number']);
    }

    public function test_non_admin_cannot_fetch_employee_loans_json(): void
    {
        $user = $this->createEmployee('Regular User', '777001');
        $employee = $this->createEmployee('Target Employee', '555001');
        $hrdEmployee = HrdEmployee::where('employee_id', $employee->employee_id)->firstOrFail();

        $response = $this->actingAs($user)->getJson(route('smart.karyawan.loans', $hrdEmployee));

        $response->assertStatus(403);
    }

    public function test_employee_loans_returns_empty_when_no_loans(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee('Empty Employee', '666001');
        $hrdEmployee = HrdEmployee::where('employee_id', $employee->employee_id)->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('smart.karyawan.loans', $hrdEmployee));

        $response->assertStatus(200);
        $response->assertJson([
            'active' => [],
            'history' => [],
        ]);
    }
}
