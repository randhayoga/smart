<?php

namespace Database\Seeders;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\TbProject;
use Illuminate\Database\Seeder;

class DummyNonConsumableAssetSeeder extends Seeder
{
    /**
     * Run the non-consumable dummy Asset database seeds.
     */
    public function run(): void
    {
        $assets = [
            [
                'number' => '00001-FUR-KK-CFS-PTRE-26',
                'lot_number' => 'LOT-0001-26-FUR-KK-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00002-FUR-KK-CFS-PTRE-26',
                'lot_number' => 'LOT-0001-26-FUR-KK-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00003-FUR-KK-CFS-PTRE-26',
                'lot_number' => 'LOT-0002-26-FUR-KK-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00004-FUR-KK-CFS-PTRE-26',
                'lot_number' => 'LOT-0002-26-FUR-KK-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00001-COMP-NB-ICT-PTRE-26',
                'lot_number' => 'LOT-0001-26-COMP-NB-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'specification' => 'Ultra 5 125H, 16GB LPDDR5X, 512GB NVMe SSD',
                'vendor_code' => 'VN0002',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00002-COMP-NB-ICT-PTRE-26',
                'lot_number' => 'LOT-0001-26-COMP-NB-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'specification' => 'Ultra 7 155H, 32GB LPDDR5X, 1TB NVMe SSD',
                'vendor_code' => 'VN0003',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00005-KEN-MO-CFS-PTRE-26',
                'lot_number' => 'LOT-0001-26-KEN-MO-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1234 RE',
            ],
            [
                'number' => '00006-KEN-MO-CFS-PTRE-26',
                'lot_number' => 'LOT-0001-26-KEN-MO-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1235 RE',
            ],
            [
                'number' => '00007-KEN-MO-CFS-PTRE-26',
                'lot_number' => 'LOT-0002-26-KEN-MO-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1236 RE',
            ],
            [
                'number' => '00008-KEN-MO-CFS-PTRE-26',
                'lot_number' => 'LOT-0002-26-KEN-MO-0001',
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1237 RE',
            ],
        ];

        foreach ($assets as $data) {
            $lot = Lot::where('number', $data['lot_number'])->first();
            if (!$lot) {
                continue;
            }

            $burden = ['Corporate', 'Project'][array_rand(['Corporate', 'Project'])];
            $projectId = $burden === 'Project' ? TbProject::inRandomOrder()->first()?->id : null;

            $vendorId = null;
            if (!empty($data['vendor_code'])) {
                $vendorId = \App\Models\Master\Vendor::where('code', $data['vendor_code'])->value('id');
            } elseif (!empty($data['vendor_id'])) {
                $vendorId = \App\Models\Master\Vendor::where('id', $data['vendor_id'])->value('id');
            }

            Asset::updateOrCreate(
                ['number' => $data['number']],
                [
                    'lot_id' => $lot->id,
                    'location_id' => $lot->location_id,
                    'status' => $data['status'],
                    'condition' => $data['condition'],
                    'borrowing_type' => 'LT',
                    'classification' => ((float)($lot->unit_price ?? 0) > 5000000 ? 'Aset' : 'Inventaris'),
                    'price' => $lot->unit_price,
                    'image_url' => $lot->image_url,
                    'vehicle_registration' => $data['vehicle_registration'],
                    'burden' => $burden,
                    'project_id' => $projectId,
                    'comp_specification' => $data['specification'] ?? null,
                    'legacy_vendor_id' => $vendorId,
                ]
            );
        }
    }
}
