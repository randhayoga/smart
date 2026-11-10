<?php

namespace Database\Seeders;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetLifecycle;
use App\Models\Inventory\AssetMaintenance;
use App\Models\Inventory\InventoryLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Maintenance & QC Showcase Seeder
 *
 * Seeds realistic maintenance histories and Quality Control (QC) inspection states
 * for showcase across non-consumable assets.
 */
class DummyMaintenanceQcSeeder extends Seeder
{
    public const SHOWCASE_MAINT_REFS = [
        'SHOWCASE-MAINT-001',
        'SHOWCASE-MAINT-002',
        'SHOWCASE-MAINT-003',
    ];

    public function run(): void
    {
        $adminUser = User::where('employee_id', '255578')->first() ?? User::where('employee_id', '265656')->first() ?? User::first();
        if (!$adminUser) {
            return;
        }

        // 1. Safe Idempotent Cleanup of prior maintenance and QC records
        AssetMaintenance::whereIn('reference', self::SHOWCASE_MAINT_REFS)->delete();
        InventoryLog::where('note', 'LIKE', '%QC%')->delete();
        InventoryLog::where('note', 'LIKE', '%SHOWCASE_QC%')->delete();

        // 2. Select eligible non-consumable assets
        $assets = Asset::with('lot.model')
            ->whereHas('lot.model.type', fn ($q) => $q->where('is_consumable', false))
            ->where('status', 'Tersedia')
            ->take(4)
            ->get();

        if ($assets->count() < 3) {
            $this->command?->warn('DummyMaintenanceQcSeeder: Not enough non-consumable assets found.');
            return;
        }

        $asset1 = $assets[0];
        $asset2 = $assets[1];
        $asset3 = $assets[2];
        $asset4 = $assets[3] ?? $assets[0];

        // Clean up prior showcase QC lifecycles for these assets
        AssetLifecycle::where('action_type', 'Quality Control')
            ->whereIn('asset_id', [$asset1->id, $asset2->id, $asset4->id])
            ->delete();

        // 3. Seed Asset Maintenance Records
        // Record 1: Routine Maintenance (Internal)
        AssetMaintenance::create([
            'asset_id' => $asset1->id,
            'maintainer_id' => $adminUser->id,
            'user_id' => null,
            'type' => 'Perawatan Berkala',
            'source' => 'Internal',
            'reference' => 'SHOWCASE-MAINT-001',
            'problem' => 'Pembersihan debu saluran pendingin fan dan evaluasi suhu CPU',
            'solution' => 'Pembersihan heatsink komprehensif dan penggantian thermal paste Arctic MX-4',
            'cost' => 150000,
            'date' => Carbon::create(2026, 11, 2),
            'image_url' => null,
        ]);

        // Record 2: Corrective Repair (External Service Vendor)
        AssetMaintenance::create([
            'asset_id' => $asset2->id,
            'maintainer_id' => $adminUser->id,
            'user_id' => null,
            'type' => 'Perbaikan',
            'source' => 'Eksternal',
            'reference' => 'SHOWCASE-MAINT-002',
            'problem' => 'Layar berkedip (flickering) dan muncul garis horizontal pada display',
            'solution' => 'Penggantian modul display panel LCD baru melalui servis resmi vendor',
            'cost' => 850000,
            'date' => Carbon::create(2026, 10, 20),
            'image_url' => null,
        ]);

        // Record 3: Hardware Replacement (External Service Vendor)
        AssetMaintenance::create([
            'asset_id' => $asset3->id,
            'maintainer_id' => $adminUser->id,
            'user_id' => null,
            'type' => 'Penggantian Komponen',
            'source' => 'Eksternal',
            'reference' => 'SHOWCASE-MAINT-003',
            'problem' => 'Lampu proyektor telah mencapai batas umur pemakaian (hours limit) dan redup',
            'solution' => 'Pemasangan unit lampu bohlam OEM baru serta kalibrasi fokus optik',
            'cost' => 1450000,
            'date' => Carbon::create(2026, 11, 7),
            'image_url' => null,
        ]);

        // 4. Seed Quality Control (QC) inspection states in asset_lifecycles (NOT in inventory_logs)
        // Asset 1: QC Pass
        $qcDate1 = Carbon::create(2026, 11, 8, 10, 30, 0);
        Asset::withoutLifecycleLogging(function () use ($asset1, $qcDate1) {
            $asset1->update([
                'qc_pass' => true,
                'last_qc' => $qcDate1,
                'condition' => 'Bagus',
            ]);
        });
        AssetLifecycle::create([
            'asset_id' => $asset1->id,
            'action_type' => 'Quality Control',
            'status' => $asset1->status,
            'condition' => 'Bagus',
            'location_id' => $asset1->location_id,
            'start_date' => $qcDate1,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'QC Dilakukan, hasil: QC Pass',
            'previous_state' => ['qc_pass' => null, 'last_qc' => null],
            'new_state' => ['qc_pass' => true, 'last_qc' => $qcDate1->toISOString()],
        ]);

        // Asset 2: QC Pass
        $qcDate2 = Carbon::create(2026, 11, 4, 9, 15, 0);
        Asset::withoutLifecycleLogging(function () use ($asset2, $qcDate2) {
            $asset2->update([
                'qc_pass' => true,
                'last_qc' => $qcDate2,
                'condition' => 'Bagus',
            ]);
        });
        AssetLifecycle::create([
            'asset_id' => $asset2->id,
            'action_type' => 'Quality Control',
            'status' => $asset2->status,
            'condition' => 'Bagus',
            'location_id' => $asset2->location_id,
            'start_date' => $qcDate2,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'QC Dilakukan, hasil: QC Pass',
            'previous_state' => ['qc_pass' => null, 'last_qc' => null],
            'new_state' => ['qc_pass' => true, 'last_qc' => $qcDate2->toISOString()],
        ]);

        // Asset 4: QC Fail (Defective / Damaged)
        $qcDate3 = Carbon::create(2026, 11, 6, 14, 0, 0);
        Asset::withoutLifecycleLogging(function () use ($asset4, $qcDate3) {
            $asset4->update([
                'qc_pass' => false,
                'last_qc' => $qcDate3,
                'condition' => 'Rusak',
            ]);
        });
        AssetLifecycle::create([
            'asset_id' => $asset4->id,
            'action_type' => 'Quality Control',
            'status' => $asset4->status,
            'condition' => 'Rusak',
            'location_id' => $asset4->location_id,
            'start_date' => $qcDate3,
            'end_date' => null,
            'actor_id' => $adminUser->id,
            'note' => 'QC Dilakukan, hasil: QC Fail',
            'previous_state' => ['qc_pass' => null, 'condition' => 'Bagus'],
            'new_state' => ['qc_pass' => false, 'condition' => 'Rusak', 'last_qc' => $qcDate3->toISOString()],
        ]);
    }
}
