<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use Illuminate\Support\Facades\Storage;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'number' => '00001-FUR-KK-CFS-PTRE-26',
                'lot_id' => 6,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00002-FUR-KK-CFS-PTRE-26',
                'lot_id' => 6,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],

            [
                'number' => '00003-FUR-KK-CFS-PTRE-26',
                'lot_id' => 7,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00004-FUR-KK-CFS-PTRE-26',
                'lot_id' => 7,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => null,
            ],

            [
                'number' => '00001-COMP-NB-ICT-PTRE-26',
                'lot_id' => 3,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'specification' => 'Ultra 5 125H, 16GB LPDDR5X, 512GB NVMe SSD',
                'vendor_id' => 2,
                'vehicle_registration' => null,
            ],
            [
                'number' => '00002-COMP-NB-ICT-PTRE-26',
                'lot_id' => 3,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'specification' => 'Ultra 7 155H, 32GB LPDDR5X, 1TB NVMe SSD',
                'vendor_id' => 3,
                'vehicle_registration' => null,
            ],

            [
                'number' => '00005-KEN-MO-CFS-PTRE-26',
                'lot_id' => 4,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1234 RE',
            ],
            [
                'number' => '00006-KEN-MO-CFS-PTRE-26',
                'lot_id' => 4,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1235 RE',
            ],

            [
                'number' => '00007-KEN-MO-CFS-PTRE-26',
                'lot_id' => 5,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1236 RE',
            ],
            [
                'number' => '00008-KEN-MO-CFS-PTRE-26',
                'lot_id' => 5,
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'vehicle_registration' => 'B 1237 RE',
            ],
        ];

        foreach ($units as $data) {
            $lot = Lot::find($data['lot_id']);
            if (!$lot) {
                continue;
            }

            // Use lot image directly without copying
            $unitImagePath = $lot->image_url;

            $burden = ['Corporate', 'Project'][array_rand(['Corporate', 'Project'])];
            $projectId = $burden === 'Project' ? \App\Models\TbProject::inRandomOrder()->first()?->id : null;

            Unit::updateOrCreate(
                ['number' => $data['number']],
                [
                    'lot_id' => $data['lot_id'],
                    'location_id' => $lot->location_id,
                    'status' => $data['status'],
                    'condition' => $data['condition'],
                    'type' => 'LT',
                    'classification' => ((float)($lot->unit_price ?? 0) > 5000000 ? 'Aset' : 'Inventaris'),
                    'price' => $lot->unit_price,
                    'image_url' => $unitImagePath,
                    'vehicle_registration' => $data['vehicle_registration'],
                    'burden' => $burden,
                    'project_id' => $projectId,
                    'specification' => $data['specification'] ?? null,
                    'vendor_id' => $data['vendor_id'] ?? null,
                ]
            );
        }
    }
}
