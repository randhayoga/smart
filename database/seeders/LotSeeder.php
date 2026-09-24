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
                'number' => 'LOT-0001-26-ATK-HVS4-0001',
                'barang_id' => 1,
                'organizer_id' => 1,
                'vendor_id' => 1,
                'location_id' => 3,
                'initial_quantity' => 75,
                'current_quantity' => 75,
                'po_number' => 'PO-01',
                'date_of_receipt' => '01/03/2026',
                'unit_price' => 60000,
                'image_url' => '/database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'LOT-0002-26-ATK-HVS4-0001',
                'barang_id' => 1,
                'organizer_id' => 1,
                'vendor_id' => 1,
                'location_id' => 3,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'po_number' => 'PO-02',
                'date_of_receipt' => '02/04/2026',
                'unit_price' => 60000,
                'image_url' => '/database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'LOT-0001-26-COMP-NB-0001',
                'barang_id' => 6,
                'organizer_id' => 2,
                'vendor_id' => 1,
                'location_id' => 3,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-03',
                'date_of_receipt' => '22/05/2026',
                'unit_price' => 12500000,
                'image_url' => '/database/seeders/assets/acer.jpg',
            ],
            [
                'number' => 'LOT-0001-26-KEN-MO-0001',
                'barang_id' => 7,
                'organizer_id' => 1,
                'vendor_id' => 2,
                'location_id' => 6,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-04',
                'date_of_receipt' => '02/05/2026',
                'unit_price' => 650000000,
                'image_url' => '/database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'LOT-0002-26-KEN-MO-0001',
                'barang_id' => 7,
                'organizer_id' => 1,
                'vendor_id' => 3,
                'location_id' => 6,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-05',
                'date_of_receipt' => '03/06/2026',
                'unit_price' => 650000000,
                'image_url' => '/database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'LOT-0001-26-FUR-KK-0001',
                'barang_id' => 4,
                'organizer_id' => 1,
                'vendor_id' => 4,
                'location_id' => 3,
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-06',
                'date_of_receipt' => '02/05/2026',
                'unit_price' => 1000000,
                'image_url' => '/database/seeders/assets/ikea.jpg',
            ],
            [
                'number' => 'LOT-0002-26-FUR-KK-0001',
                'barang_id' => 4,
                'organizer_id' => 1,
                'vendor_id' => 4,
                'location_id' => 5,
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
                if (!Storage::disk('local')->exists('inventory')) {
                    Storage::disk('local')->makeDirectory('inventory');
                }
                Storage::disk('local')->put($destinationPath, File::get($sourcePath));
            }

            $burden = ['Corporate', 'Project'][array_rand(['Corporate', 'Project'])];
            $projectId = null;
            if ($burden === 'Project') {
                $projectId = \App\Models\TbProject::inRandomOrder()->first()?->id;
            }

            $parts = explode('-', $data['number'], 4);
            $barangNumber = $parts[3] ?? null;
            $barangId = ($barangNumber ? \App\Models\Inventory\Barang::where('number', $barangNumber)->value('id') : null) ?? $data['barang_id'];
            $organizerId = \App\Models\Master\Organizer::where('id', $data['organizer_id'])->value('id') ?? \App\Models\Master\Organizer::first()?->id ?? $data['organizer_id'];
            $vendorId = \App\Models\Master\Vendor::where('id', $data['vendor_id'])->value('id') ?? \App\Models\Master\Vendor::first()?->id ?? $data['vendor_id'];
            $locationId = \App\Models\Master\Location::where('id', $data['location_id'])->value('id') ?? \App\Models\Master\Location::first()?->id ?? $data['location_id'];

            $lot = Lot::updateOrCreate(
                ['number' => $data['number']],
                [
                    'barang_id' => $barangId,
                    'organizer_id' => $organizerId,
                    'vendor_id' => $vendorId,
                    'location_id' => $locationId,
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
