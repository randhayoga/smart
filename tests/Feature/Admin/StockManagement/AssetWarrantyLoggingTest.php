<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetWarrantyLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function createAsset(array $overrides = []): Asset
    {
        return Asset::factory()->create(array_merge([
            'status' => 'Tersedia',
            'condition' => 'Baik',
            'warranty_date' => null,
        ], $overrides));
    }

    public function test_logs_warranty_date_change_from_null_to_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = $this->createAsset(['warranty_date' => null]);

        $asset->update(['warranty_date' => '2027-05-15']);

        $log = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Perubahan garansi')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame("Tanggal berakhir garansi diubah dari '-' menjadi '15-05-2027'.", $log->note);
        $this->assertSame(['warranty_date' => null], $log->previous_state);
        $this->assertSame(['warranty_date' => '2027-05-15'], $log->new_state);
        $this->assertSame($user->id, $log->actor_id);
    }

    public function test_logs_warranty_date_change_between_dates(): void
    {
        $asset = $this->createAsset(['warranty_date' => '2026-12-01']);

        $asset->update(['warranty_date' => '2028-01-20']);

        $log = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Perubahan garansi')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame("Tanggal berakhir garansi diubah dari '01-12-2026' menjadi '20-01-2028'.", $log->note);
        $this->assertSame(['warranty_date' => '2026-12-01'], $log->previous_state);
        $this->assertSame(['warranty_date' => '2028-01-20'], $log->new_state);
    }

    public function test_logs_warranty_date_cleared_to_null(): void
    {
        $asset = $this->createAsset(['warranty_date' => '2027-03-10']);

        $asset->update(['warranty_date' => null]);

        $log = AssetLifecycle::where('asset_id', $asset->id)
            ->where('action_type', 'Perubahan garansi')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame("Tanggal berakhir garansi diubah dari '10-03-2027' menjadi '-'.", $log->note);
        $this->assertSame(['warranty_date' => '2027-03-10'], $log->previous_state);
        $this->assertSame(['warranty_date' => null], $log->new_state);
    }

    public function test_does_not_log_warranty_date_for_pending_asset(): void
    {
        $asset = $this->createAsset([
            'warranty_date' => null,
        ]);

        $asset->update([
            'approval_status' => 'Pending:DM',
            'warranty_date' => '2027-05-15',
        ]);

        $this->assertSame(
            0,
            AssetLifecycle::where('asset_id', $asset->id)
                ->where('action_type', 'Perubahan garansi')
                ->count()
        );
    }
}
