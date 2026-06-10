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
            // LOT-2026-FUR-KUR-0001-0002 (Lot ID: 7)
            [
                'number' => 'LOT-2026-FUR-KUR-0001-0002-U01',
                'lot_id' => 7,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],
            [
                'number' => 'LOT-2026-FUR-KUR-0001-0002-U02',
                'lot_id' => 7,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],

            // LOT-2026-FUR-KUR-0001-0001 (Lot ID: 6)
            [
                'number' => 'LOT-2026-FUR-KUR-0001-0001-U01',
                'lot_id' => 6,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],
            [
                'number' => 'LOT-2026-FUR-KUR-0001-0001-U02',
                'lot_id' => 6,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],

            // LOT-2026-ELE-LAP-0001-0001 (Lot ID: 3)
            [
                'number' => 'LOT-2026-ELE-LAP-0001-0001-U01',
                'lot_id' => 3,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],
            [
                'number' => 'LOT-2026-ELE-LAP-0001-0001-U02',
                'lot_id' => 3,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => null,
            ],

            // LOT-2026-KEN-MOB-0001-0002 (Lot ID: 5)
            [
                'number' => 'LOT-2026-KEN-MOB-0001-0002-U01',
                'lot_id' => 5,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => 'B 1234 RE',
            ],
            [
                'number' => 'LOT-2026-KEN-MOB-0001-0002-U02',
                'lot_id' => 5,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => 'B 1235 RE',
            ],

            // LOT-2026-KEN-MOB-0001-0001 (Lot ID: 4)
            [
                'number' => 'LOT-2026-KEN-MOB-0001-0001-U01',
                'lot_id' => 4,
                'status' => 'tersedia',
                'condition' => 'Baik',
                'vehicle_registration' => 'B 1236 RE',
            ],
            [
                'number' => 'LOT-2026-KEN-MOB-0001-0001-U02',
                'lot_id' => 4,
                'status' => 'tersedia',
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
