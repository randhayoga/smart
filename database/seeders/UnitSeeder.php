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
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00002-FUR-KK-CFS-PTRE-26',
                'lot_id' => 6,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],

            [
                'number' => '00003-FUR-KK-CFS-PTRE-26',
                'lot_id' => 7,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00004-FUR-KK-CFS-PTRE-26',
                'lot_id' => 7,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],

            [
                'number' => '00001-COMP-NB-ICT-PTRE-26',
                'lot_id' => 3,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],
            [
                'number' => '00002-COMP-NB-ICT-PTRE-26',
                'lot_id' => 3,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],

            [
                'number' => '00001-KEN-MO-CFS-PTRE-26',
                'lot_id' => 4,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => 'B 1234 RE',
            ],
            [
                'number' => '00002-KEN-MO-CFS-PTRE-26',
                'lot_id' => 4,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => 'B 1235 RE',
            ],

            [
                'number' => '00003-KEN-MO-CFS-PTRE-26',
                'lot_id' => 5,
                'status' => 'Tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => 'B 1236 RE',
            ],
            [
                'number' => '00004-KEN-MO-CFS-PTRE-26',
                'lot_id' => 5,
                'status' => 'Tersedia',
                'condition' => 'Baik',
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

            Unit::updateOrCreate(
                ['number' => $data['number']],
                [
                    'lot_id' => $data['lot_id'],
                    'location_id' => $lot->location_id,
                    'floor_id' => $lot->floor_id,
                    'room_id' => $lot->room_id,
                    'status' => $data['status'],
                    'condition' => $data['condition'],
                    'price' => $lot->unit_price,
                    'image_url' => $unitImagePath,
                    'vehicle_registration' => $data['vehicle_registration'],
                ]
            );
        }
    }
}
