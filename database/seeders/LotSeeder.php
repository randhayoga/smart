<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\Lot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lots = [
            [
                'number' => 'LOT-2026-ATK-KER-0001-0002',
                'barang_id' => 1,
                'organizer_id' => 1,
                'vendor_id' => 1,
                'location_id' => 1,
                'floor_id' => 1,
                'room_id' => 1,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'po_number' => 'PO-02',
                'date_of_receipt' => '02/04/2026',
                'unit_price' => 60000,
                'image_url' => '/database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'LOT-2026-ATK-KER-0001-0001',
                'barang_id' => 1,
                'organizer_id' => 1,
                'vendor_id' => 1,
                'location_id' => 1,
                'floor_id' => 1,
                'room_id' => 1,
                'initial_quantity' => 75,
                'current_quantity' => 75,
                'po_number' => 'PO-01',
                'date_of_receipt' => '01/03/2026',
                'unit_price' => 60000,
                'image_url' => '/database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'LOT-2026-ELE-LAP-0001-0001',
                'barang_id' => 6,
                'organizer_id' => 1,
                'vendor_id' => 1,
                'location_id' => 1,
                'floor_id' => 1,
                'room_id' => 1,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-03',
                'date_of_receipt' => '22/05/2026',
                'unit_price' => 12500000,
                'image_url' => '/database/seeders/assets/acer.jpg',
            ],
            [
                'number' => 'LOT-2026-KEN-MOB-0001-0001',
                'barang_id' => 7,
                'organizer_id' => 1,
                'vendor_id' => 2,
                'location_id' => 2,
                'floor_id' => null,
                'room_id' => null,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-04',
                'date_of_receipt' => '02/05/2026',
                'unit_price' => 650000000,
                'image_url' => '/database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'LOT-2026-KEN-MOB-0001-0002',
                'barang_id' => 7,
                'organizer_id' => 1,
                'vendor_id' => 3,
                'location_id' => 2,
                'floor_id' => null,
                'room_id' => null,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-05',
                'date_of_receipt' => '03/06/2026',
                'unit_price' => 650000000,
                'image_url' => '/database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'LOT-2026-FUR-KUR-0001-0001',
                'barang_id' => 4,
                'organizer_id' => 1,
                'vendor_id' => 4,
                'location_id' => 1,
                'floor_id' => 1,
                'room_id' => 1,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-06',
                'date_of_receipt' => '02/05/2026',
                'unit_price' => 1000000,
                'image_url' => '/database/seeders/assets/ikea.jpg',
            ],
            [
                'number' => 'LOT-2026-FUR-KUR-0001-0002',
                'barang_id' => 4,
                'organizer_id' => 1,
                'vendor_id' => 4,
                'location_id' => 1,
                'floor_id' => 2,
                'room_id' => 2,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-07',
                'date_of_receipt' => '05/05/2026',
                'unit_price' => 1000000,
                'image_url' => '/database/seeders/assets/ikea.jpg',
            ],
        ];

        foreach ($lots as $data) {
            $sourcePath = base_path(ltrim($data['image_url'], '/'));
            if (!File::exists($sourcePath)) {
                if (str_ends_with($sourcePath, '.jpg')) {
                    $fallback = substr($sourcePath, 0, -4) . '.jpeg';
                    if (File::exists($fallback)) {
                        $sourcePath = $fallback;
                    }
                }
            }

            $destinationPath = 'inventory/' . basename($sourcePath);

            if (File::exists($sourcePath)) {
                if (!Storage::disk('public')->exists('inventory')) {
                    Storage::disk('public')->makeDirectory('inventory');
                }
                Storage::disk('public')->put($destinationPath, File::get($sourcePath));
            }

            $burden = ['Corporate', 'Project'][array_rand(['Corporate', 'Project'])];
            $projectId = null;
            if ($burden === 'Project') {
                $projectId = \App\Models\TbProject::inRandomOrder()->first()?->id;
            }

            $lot = Lot::updateOrCreate(
                ['number' => $data['number']],
                [
                    'barang_id' => $data['barang_id'],
                    'organizer_id' => $data['organizer_id'],
                    'vendor_id' => $data['vendor_id'],
                    'location_id' => $data['location_id'],
                    'floor_id' => $data['floor_id'],
                    'room_id' => $data['room_id'],
                    'initial_quantity' => $data['initial_quantity'],
                    'current_quantity' => $data['current_quantity'],
                    'po_number' => $data['po_number'],
                    'date_of_receipt' => Carbon::createFromFormat('d/m/Y', $data['date_of_receipt']),
                    'unit_price' => $data['unit_price'],
                    'image_url' => $destinationPath,
                    'burden' => $burden,
                    'project_id' => $projectId,
                ]
            );
        }
    }
}
