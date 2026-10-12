<?php

namespace Database\Seeders;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DummyNonConsumableLotSeeder extends Seeder
{
    /**
     * Run the non-consumable dummy Lot database seeds.
     */
    public function run(): void
    {
        $lots = [
            [
                'number' => 'LOT-0001-26-COMP-NB-0001',
                'barang_number' => 'COMP-NB-0001',
                'organizer_name' => 'ICT',
                'vendor_code' => null,
                'location_name' => 'Ruang IFS Departemen',
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-03',
                'date_of_receipt' => '22/05/2026',
                'unit_price' => 12500000,
                'image_url' => '/database/seeders/assets/acer.jpg',
            ],
            [
                'number' => 'LOT-0001-26-KEN-MO-0001',
                'barang_number' => 'KEN-MO-0001',
                'organizer_name' => 'CFS',
                'vendor_code' => 'VN0001',
                'location_name' => 'Site A',
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-04',
                'date_of_receipt' => '02/05/2026',
                'unit_price' => 650000000,
                'image_url' => '/database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'LOT-0002-26-KEN-MO-0001',
                'barang_number' => 'KEN-MO-0001',
                'organizer_name' => 'CFS',
                'vendor_code' => 'VN0002',
                'location_name' => 'Site A',
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-05',
                'date_of_receipt' => '03/06/2026',
                'unit_price' => 650000000,
                'image_url' => '/database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'LOT-0001-26-FUR-KK-0001',
                'barang_number' => 'FUR-KK-0001',
                'organizer_name' => 'CFS',
                'vendor_code' => 'VN0003',
                'location_name' => 'Ruang IFS Departemen',
                'initial_quantity' => 0,
                'current_quantity' => 0,
                'po_number' => 'PO-06',
                'date_of_receipt' => '02/05/2026',
                'unit_price' => 1000000,
                'image_url' => '/database/seeders/assets/ikea.jpg',
            ],
            [
                'number' => 'LOT-0002-26-FUR-KK-0001',
                'barang_number' => 'FUR-KK-0001',
                'organizer_name' => 'CFS',
                'vendor_code' => 'VN0003',
                'location_name' => 'Ruang Mega Mendung',
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

            $barang = Barang::where('number', $data['barang_number'])->first();
            if (!$barang) {
                continue;
            }

            $organizer = Organizer::where('name', $data['organizer_name'])->first() ?? Organizer::first();
            $beragamVendorId = Vendor::where('name', 'Beragam')->value('id');
            $vendor = !empty($data['vendor_code'])
                ? (Vendor::where('code', $data['vendor_code'])->first() ?? Vendor::first())
                : (Vendor::where('id', $beragamVendorId)->first() ?? Vendor::first());

            $location = Location::where('name', $data['location_name'])->first()
                ?? Location::where('name', 'like', '%' . $data['location_name'] . '%')->first()
                ?? Location::first();

            Lot::updateOrCreate(
                ['number' => $data['number']],
                [
                    'barang_id' => $barang->id,
                    'organizer_id' => $organizer?->id,
                    'vendor_id' => $vendor?->id,
                    'legacy_vendor_id' => $vendor?->id,
                    'location_id' => $location?->id,
                    'initial_quantity' => $data['initial_quantity'],
                    'current_quantity' => $data['current_quantity'],
                    'po_number' => $data['po_number'],
                    'date_of_receipt' => Carbon::createFromFormat('d/m/Y', $data['date_of_receipt']),
                    'unit_price' => $data['unit_price'],
                    'image_url' => $destinationPath,
                    'burden' => null,
                    'project_id' => null,
                ]
            );
        }
    }
}
