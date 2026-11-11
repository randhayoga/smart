<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\AssetMaintenance;
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

class AssetMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        $perm = Permission::firstOrCreate(
            ['name' => 'inventory.maintenance'],
            ['label' => 'Record and Manage Asset Maintenance History', 'group' => 'inventory']
        );
        $adminRole = Role::firstOrCreate(['name' => 'admin_cfs'], ['label' => 'Admin CFS']);
        $adminRole->permissions()->syncWithoutDetaching([$perm->id]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin_cfs');

        $this->regularUser = User::factory()->create();

        // 2. Asset test fixture
        $organizer = Organizer::firstOrCreate(['name' => 'IT Dept'], ['code' => 'IT']);
        $category = Category::firstOrCreate(['name' => 'Computer'], ['code' => 'COMP']);
        $type = Type::firstOrCreate(['name' => 'Laptop', 'category_id' => $category->id], ['code' => 'LP']);
        $brand = Brand::firstOrCreate(['name' => 'Lenovo']);
        $uom = Uom::firstOrCreate(['name' => 'Unit']);
        $location = Location::firstOrCreate(['name' => 'Server Room'], ['full_name' => 'Server Room HQ']);

        $model = InventoryModel::create([
            'number' => 'MDL-TEST-001',
            'name' => 'ThinkPad P1',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'is_consumable' => false,
        ]);

        $lot = Lot::create([
            'number' => 'LOT-2026-999',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'ST',
            'burden' => 'Corporate',
            'purchase_grant_date' => now(),
        ]);

        $this->asset = Asset::create([
            'number' => 'AST-TEST-00001',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'classification' => 'Aset',
            'borrowing_type' => 'ST',
            'burden' => 'Corporate',
        ]);
    }

    public function test_user_without_maintenance_permission_cannot_access_or_submit_maintenance(): void
    {
        config(['app.disable_test_admin_bypass' => true]);

        // 1. GET maintenances list
        $response = $this->actingAs($this->regularUser)->getJson(
            route('smart.inventory.assets.maintenances.index', $this->asset->id)
        );
        $response->assertForbidden();

        // 2. POST maintenance
        $postResponse = $this->actingAs($this->regularUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            [
                'source' => 'SMART',
                'type' => 'Preventive',
                'user_id' => $this->regularUser->id,
                'date' => now()->format('Y-m-d'),
                'cost' => 50000,
                'problem' => 'Routine cleaning',
                'solution' => 'Dust removal and thermal paste reapplication',
                'condition' => 'Tidak Berubah',
            ]
        );
        $postResponse->assertForbidden();
    }

    public function test_admin_can_submit_maintenance_with_source_smart_and_unchanged_condition(): void
    {
        $payload = [
            'source' => 'SMART',
            'type' => 'Preventive',
            'user_id' => $this->regularUser->id,
            'date' => '2026-11-04',
            'cost' => 150000,
            'problem' => 'Pembersihan kipas berkala dan pengecekan baterai',
            'solution' => 'Kipas dibersihkan dan pasta prosesor diganti baru',
            'condition' => 'Tidak Berubah',
        ];

        $initialConditionCount = AssetLifecycle::where('asset_id', $this->asset->id)
            ->where('action_type', 'Perubahan kondisi')
            ->count();

        $response = $this->actingAs($this->adminUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            $payload
        );

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Riwayat perawatan berhasil disimpan.',
            'asset_condition' => 'Bagus',
            'maintenance' => [
                'type' => 'Preventive',
                'source' => 'SMART',
                'reference' => null,
                'cost' => 150000,
            ],
        ]);

        $this->assertDatabaseHas('asset_maintenances', [
            'asset_id' => $this->asset->id,
            'maintainer_id' => $this->adminUser->id,
            'user_id' => $this->regularUser->id,
            'type' => 'Preventive',
            'source' => 'SMART',
            'cost' => 150000,
            'problem' => 'Pembersihan kipas berkala dan pengecekan baterai',
        ]);

        // Condition after maintenance was 'Tidak Berubah', asset condition remains 'Bagus'
        $this->assertEquals('Bagus', $this->asset->fresh()->condition);

        // Crucial requirement: No condition change log should be created
        $finalConditionCount = AssetLifecycle::where('asset_id', $this->asset->id)
            ->where('action_type', 'Perubahan kondisi')
            ->count();
        $this->assertEquals($initialConditionCount, $finalConditionCount);

        // A 'Pemeliharaan' lifecycle log must be created
        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $this->asset->id,
            'action_type' => 'Pemeliharaan',
            'actor_id' => $this->adminUser->id,
        ]);
    }

    public function test_condition_unchanged_when_same_as_before_even_if_explicitly_selected(): void
    {
        // Current condition is 'Bagus'. Admin explicitly selects 'Bagus'.
        $this->assertEquals('Bagus', $this->asset->condition);

        $initialConditionCount = AssetLifecycle::where('asset_id', $this->asset->id)
            ->where('action_type', 'Perubahan kondisi')
            ->count();

        $response = $this->actingAs($this->adminUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            [
                'source' => 'SMART',
                'type' => 'Preventive',
                'user_id' => $this->regularUser->id,
                'date' => '2026-11-04',
                'cost' => 50000,
                'problem' => 'Pengecekan rutin kabel adaptor',
                'solution' => 'Kabel adaptor masih berfungsi dengan baik',
                'condition' => 'Bagus',
            ]
        );

        $response->assertStatus(201);

        // No condition change log should be created
        $finalConditionCount = AssetLifecycle::where('asset_id', $this->asset->id)
            ->where('action_type', 'Perubahan kondisi')
            ->count();
        $this->assertEquals($initialConditionCount, $finalConditionCount);
    }

    public function test_condition_updates_and_logs_when_condition_changes_from_rusak_to_bagus(): void
    {
        // Set asset condition to 'Rusak'
        $this->asset->condition = 'Rusak';
        $this->asset->save();

        $response = $this->actingAs($this->adminUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            [
                'source' => 'TiSu',
                'reference' => 'TISU-2026-888',
                'type' => 'Corrective',
                'user_id' => $this->regularUser->id,
                'date' => '2026-11-04',
                'cost' => 450000,
                'problem' => 'Keyboard beberapa tombol tidak responsif',
                'solution' => 'Penggantian modul keyboard dengan part original baru',
                'condition' => 'Bagus',
            ]
        );

        $response->assertStatus(201);
        $this->assertEquals('Bagus', $this->asset->fresh()->condition);

        // Condition changed from 'Rusak' to 'Bagus', so condition change log was recorded
        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $this->asset->id,
            'action_type' => 'Perubahan kondisi',
            'condition' => 'Bagus',
        ]);

        // Pemeliharaan lifecycle was also recorded
        $this->assertDatabaseHas('asset_lifecycles', [
            'asset_id' => $this->asset->id,
            'action_type' => 'Pemeliharaan',
            'condition' => 'Bagus',
        ]);
    }

    public function test_reference_is_required_when_source_is_tisu(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            [
                'source' => 'TiSu',
                'reference' => '',
                'type' => 'Preventive',
                'user_id' => $this->regularUser->id,
                'date' => '2026-11-04',
                'cost' => 10000,
                'problem' => 'Test',
                'solution' => 'Test',
                'condition' => 'Tidak Berubah',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reference']);
    }

    public function test_proof_file_can_be_uploaded_with_maintenance(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('nota_perawatan.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->adminUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            [
                'source' => 'SMART',
                'type' => 'Corrective',
                'user_id' => $this->regularUser->id,
                'date' => '2026-11-04',
                'cost' => 200000,
                'problem' => 'Ganti baterai CMOS',
                'solution' => 'Baterai CMOS baru dipasang',
                'condition' => 'Tidak Berubah',
                'proof_file' => $file,
            ]
        );

        $response->assertStatus(201);
        $maintenance = AssetMaintenance::where('asset_id', $this->asset->id)->latest('id')->first();
        $this->assertNotNull($maintenance->image_url);
        Storage::disk('local')->assertExists($maintenance->image_url);
    }

    public function test_maintenance_history_list_returns_properly_ordered_records(): void
    {
        AssetMaintenance::create([
            'asset_id' => $this->asset->id,
            'maintainer_id' => $this->adminUser->id,
            'user_id' => $this->regularUser->id,
            'type' => 'Preventive',
            'source' => 'SMART',
            'cost' => 10000,
            'problem' => 'P1',
            'solution' => 'S1',
            'date' => '2026-10-01',
        ]);

        AssetMaintenance::create([
            'asset_id' => $this->asset->id,
            'maintainer_id' => $this->adminUser->id,
            'user_id' => $this->regularUser->id,
            'type' => 'Corrective',
            'source' => 'TiSu',
            'reference' => 'REF-02',
            'cost' => 20000,
            'problem' => 'P2',
            'solution' => 'S2',
            'date' => '2026-11-01',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(
            route('smart.inventory.assets.maintenances.index', $this->asset->id)
        );

        $response->assertOk();
        $maintenances = $response->json('maintenances');
        $this->assertCount(2, $maintenances);
        $this->assertEquals('P2', $maintenances[0]['problem']); // Later date first
        $this->assertEquals('P1', $maintenances[1]['problem']);

        // Assert users list is returned for selection
        $users = $response->json('users');
        $this->assertIsArray($users);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('disallowedStatusesProvider')]
    public function test_cannot_submit_maintenance_for_disallowed_asset_statuses(string $status): void
    {
        $this->asset->update(['status' => $status]);

        $payload = [
            'source' => 'SMART',
            'type' => 'Preventive',
            'user_id' => $this->regularUser->id,
            'date' => '2026-11-04',
            'cost' => 150000,
            'problem' => 'Kipas berisik',
            'solution' => 'Bersihkan kipas',
            'condition' => 'Tidak Berubah',
        ];

        $response = $this->actingAs($this->adminUser)->postJson(
            route('smart.inventory.assets.maintenances.store', $this->asset->id),
            $payload
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['asset']);
        $this->assertDatabaseMissing('asset_maintenances', [
            'asset_id' => $this->asset->id,
            'problem' => 'Kipas berisik',
        ]);
    }

    public static function disallowedStatusesProvider(): array
    {
        return [
            'Tidak Aktif' => ['Tidak Aktif'],
            'Belum Diverifikasi' => ['Belum Diverifikasi'],
            'Verifikasi Ditolak' => ['Verifikasi Ditolak'],
            'Lisensi Kedaluwarsa' => ['Lisensi Kedaluwarsa'],
            'Comma-separated with disallowed status' => ['Tidak Aktif, Pending:DM'],
        ];
    }
}
