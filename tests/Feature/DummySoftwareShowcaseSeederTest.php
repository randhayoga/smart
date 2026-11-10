<?php

namespace Tests\Feature;

use App\Mail\SoftwareLicenseExpiringMail;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Category;
use App\Models\User;
use Database\Seeders\DummySoftwareShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DummySoftwareShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_software_showcase_seeder_creates_all_showcase_assets_and_lots(): void
    {
        $admin = User::factory()->create([
            'employee_id' => DummySoftwareShowcaseSeeder::TARGET_ADMIN_EMPLOYEE_ID,
            'name' => 'Muhamad Radifa Azizi',
            'email' => 'muhamad.radifa@re.rekayasa.co.id',
        ]);

        $this->seed(DummySoftwareShowcaseSeeder::class);

        // 1. Verify Category & Types
        $category = Category::where('code', 'SOFT')->first();
        $this->assertNotNull($category);

        // 2. Verify Models
        $models = InventoryModel::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_MODEL_NUMBERS)->get();
        $this->assertCount(6, $models);
        foreach ($models as $model) {
            $this->assertFalse($model->is_consumable);
            $this->assertEquals($category->id, $model->type->category_id);
        }

        // 3. Verify Lots
        $lots = Lot::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_LOT_NUMBERS)->get();
        $this->assertCount(6, $lots);
        foreach ($lots as $lot) {
            $this->assertNull($lot->location_id);
        }

        // 4. Verify Assets
        $assets = Asset::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_ASSET_NUMBERS)->get();
        $this->assertCount(6, $assets);
        foreach ($assets as $asset) {
            $this->assertNull($asset->approval_status, "Asset {$asset->number} approval_status should be null when not in deactivation cycle");
            $this->assertNull($asset->location_id, "Software asset {$asset->number} should not have a physical location");
            $this->assertNull($asset->condition, "Software asset {$asset->number} should not have a physical condition");
            $this->assertNull($asset->comp_specification, "Software asset {$asset->number} comp_specification should be null");
        }

        // Check Asset 1: JetBrains All Products Pack (Expiring in 7 days)
        $jbAsset = $assets->firstWhere('number', '00091-SOFT-GEN-ICT-PTRE-26');
        $this->assertNotNull($jbAsset);
        $this->assertEquals('Tersedia', $jbAsset->status);
        $this->assertNotNull($jbAsset->license_exp_date);
        $this->assertEquals(30, $jbAsset->renewal_reminder);
        $this->assertEquals(7, (int) now()->startOfDay()->diffInDays($jbAsset->license_exp_date->startOfDay(), false));

        $jbLifecycles = \App\Models\Inventory\AssetLifecycle::where('asset_id', $jbAsset->id)->get();
        $this->assertCount(1, $jbLifecycles);
        $this->assertEquals('Aktivasi', $jbLifecycles[0]->action_type);
        $this->assertNull($jbLifecycles[0]->location_id);
        $this->assertNull($jbLifecycles[0]->condition);

        // Check Asset 3: Figma Enterprise Organization (Expired - Lisensi Kedaluwarsa)
        $figmaAsset = $assets->firstWhere('number', '00093-SOFT-GEN-ICT-PTRE-26');
        $this->assertNotNull($figmaAsset);
        $this->assertEquals('Lisensi Kedaluwarsa', $figmaAsset->status);
        $this->assertTrue($figmaAsset->license_exp_date->lt(now()->startOfDay()));
        $this->assertEquals(30, $figmaAsset->renewal_reminder);

        $figmaLifecycles = \App\Models\Inventory\AssetLifecycle::where('asset_id', $figmaAsset->id)->orderBy('id')->get();
        $this->assertCount(2, $figmaLifecycles);
        $this->assertEquals('Aktivasi', $figmaLifecycles[0]->action_type);
        $this->assertEquals('Perubahan status', $figmaLifecycles[1]->action_type);
        $this->assertEquals('Lisensi Kedaluwarsa', $figmaLifecycles[1]->status);

        // Check Asset 5: AutoCAD 2026 Civil 3D (Perpetual - null exp date)
        $cadAsset = $assets->firstWhere('number', '00095-SOFT-ENG-ICT-PTRE-26');
        $this->assertNotNull($cadAsset);
        $this->assertEquals('Tersedia', $cadAsset->status);
        $this->assertNull($cadAsset->license_exp_date);
        $this->assertNull($cadAsset->renewal_reminder);

        // Check Asset 6: SketchUp Pro 2026 (Dipinjam / LT)
        $suAsset = $assets->firstWhere('number', '00096-SOFT-ENG-ICT-PTRE-26');
        $this->assertNotNull($suAsset);
        $this->assertEquals('Dipinjam', $suAsset->status);
        $this->assertEquals('LT', $suAsset->borrowing_type);
        $this->assertEquals(30, $suAsset->renewal_reminder);

        $suLifecycles = \App\Models\Inventory\AssetLifecycle::where('asset_id', $suAsset->id)->orderBy('id')->get();
        $this->assertCount(2, $suLifecycles);
        $this->assertEquals('Aktivasi', $suLifecycles[0]->action_type);
        $this->assertEquals('Peminjaman', $suLifecycles[1]->action_type);
        $this->assertEquals('Dipinjam', $suLifecycles[1]->status);
    }

    public function test_software_showcase_seeder_sends_email_strictly_to_target_admin(): void
    {
        // 1. Create target admin
        $targetAdmin = User::factory()->create([
            'employee_id' => DummySoftwareShowcaseSeeder::TARGET_ADMIN_EMPLOYEE_ID,
            'name' => 'Muhamad Radifa Azizi',
            'email' => 'muhamad.radifa@re.rekayasa.co.id',
        ]);

        // 2. Create another admin to ensure isolation
        $otherAdmin = User::factory()->create([
            'employee_id' => '999999',
            'name' => 'Other Administrator',
            'email' => 'other.admin@rekayasa.co.id',
        ]);

        $this->seed(DummySoftwareShowcaseSeeder::class);

        // Exactly one email should be dispatched
        Mail::assertSent(SoftwareLicenseExpiringMail::class, function ($mail) use ($targetAdmin, $otherAdmin) {
            $isSentToTarget = $mail->hasTo($targetAdmin->email);
            $isSentToOther = $mail->hasTo($otherAdmin->email);
            return $isSentToTarget && !$isSentToOther && $mail->daysRemaining === 7;
        });

        // Other admin should NEVER receive the email
        Mail::assertNotSent(SoftwareLicenseExpiringMail::class, function ($mail) use ($otherAdmin) {
            return $mail->hasTo($otherAdmin->email);
        });

        // Verify target admin received in-app notifications
        $targetAdmin->refresh();
        $this->assertGreaterThanOrEqual(4, $targetAdmin->notifications->count());

        $otherAdmin->refresh();
        $this->assertEquals(0, $otherAdmin->notifications->count());
    }

    public function test_software_showcase_seeder_is_idempotent(): void
    {
        User::factory()->create([
            'employee_id' => DummySoftwareShowcaseSeeder::TARGET_ADMIN_EMPLOYEE_ID,
            'name' => 'Muhamad Radifa Azizi',
            'email' => 'muhamad.radifa@re.rekayasa.co.id',
        ]);

        // Run once
        $this->seed(DummySoftwareShowcaseSeeder::class);
        $assetCount1 = Asset::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_ASSET_NUMBERS)->count();
        $lotCount1 = Lot::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_LOT_NUMBERS)->count();
        $modelCount1 = InventoryModel::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_MODEL_NUMBERS)->count();

        // Run second time
        $this->seed(DummySoftwareShowcaseSeeder::class);
        $assetCount2 = Asset::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_ASSET_NUMBERS)->count();
        $lotCount2 = Lot::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_LOT_NUMBERS)->count();
        $modelCount2 = InventoryModel::whereIn('number', DummySoftwareShowcaseSeeder::SHOWCASE_MODEL_NUMBERS)->count();

        $this->assertEquals(6, $assetCount1);
        $this->assertEquals($assetCount1, $assetCount2);
        $this->assertEquals($lotCount1, $lotCount2);
        $this->assertEquals($modelCount1, $modelCount2);
    }
}
