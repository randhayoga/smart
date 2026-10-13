<?php

namespace Database\Seeders;

use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DummyConsumableLotSeeder extends Seeder
{
    /**
     * Run the consumable dummy Lot database seeds.
     */
    public function run(): void
    {
        $hvsModel = Model::where('number', 'ATK-HVS4-0001')->first();
        $penModel1 = Model::where('number', 'ATK-PLPH-0001')->first();

        $organizer = Organizer::where('name', 'CFS')->first() ?? Organizer::first();
        $vendor = Vendor::where('name', 'PT Surya Abadi Mandiri')->first()
            ?? Vendor::where('name', 'Beragam')->first()
            ?? Vendor::first();

        // Find an appropriate location (e.g. Ruang IFS, Ruang IFS Departemen, Lantai Mezzanine, or first available location)
        $location = Location::where('name', 'like', '%IFS%')->first()
            ?? Location::where('name', 'like', '%Mezzanine%')->first()
            ?? Location::first();

        $lots = [
            [
                'number' => 'LOT-0001-26-ATK-HVS4-0001',
                'model_id' => $hvsModel?->id,
                'organizer_id' => $organizer?->id,
                'vendor_id' => $vendor?->id,
                'location_id' => $location?->id,
                'initial_quantity' => 75,
                'current_quantity' => 75,
                'po_number' => 'PO-ATK-01',
                'date_of_receipt' => '01/03/2026',
                'unit_price' => 60000,
                'image_url' => '/database/seeders/assets/sidu.jpg',
                'burden' => 'Corporate',
                'project_id' => null,
            ],
            [
                'number' => 'LOT-0002-26-ATK-HVS4-0001',
                'model_id' => $hvsModel?->id,
                'organizer_id' => $organizer?->id,
                'vendor_id' => $vendor?->id,
                'location_id' => $location?->id,
                'initial_quantity' => 100,
                'current_quantity' => 100,
                'po_number' => 'PO-ATK-02',
                'date_of_receipt' => '02/04/2026',
                'unit_price' => 60000,
                'image_url' => '/database/seeders/assets/sidu.jpg',
                'burden' => 'Project',
                'project_id' => TbProject::inRandomOrder()->first()?->id,
            ],
            [
                'number' => 'LOT-0001-26-ATK-PLPH-0001',
                'model_id' => $penModel1?->id,
                'organizer_id' => $organizer?->id,
                'vendor_id' => $vendor?->id,
                'location_id' => $location?->id,
                'initial_quantity' => 50,
                'current_quantity' => 50,
                'po_number' => 'PO-ATK-03',
                'date_of_receipt' => '15/04/2026',
                'unit_price' => 45000,
                'image_url' => '/database/seeders/assets/snowman.jpg',
                'burden' => 'Corporate',
                'project_id' => null,
            ],
        ];

        foreach ($lots as $data) {
            if (!$data['model_id']) {
                continue;
            }

            $destinationPath = null;
            if (!empty($data['image_url'])) {
                $sourcePath = base_path(ltrim($data['image_url'], '/'));
                if (!File::exists($sourcePath) && str_ends_with($sourcePath, '.jpg')) {
                    $fallback = substr($sourcePath, 0, -4) . '.jpeg';
                    if (File::exists($fallback)) {
                        $sourcePath = $fallback;
                    }
                }

                if (File::exists($sourcePath)) {
                    $destinationPath = 'inventory/' . basename($sourcePath);
                    if (!Storage::disk('local')->exists('inventory')) {
                        Storage::disk('local')->makeDirectory('inventory');
                    }
                    Storage::disk('local')->put($destinationPath, File::get($sourcePath));
                }
            }

            Lot::updateOrCreate(
                ['number' => $data['number']],
                [
                    'model_id' => $data['model_id'],
                    'organizer_id' => $data['organizer_id'],
                    'vendor_id' => $data['vendor_id'],
                    'legacy_vendor_id' => $data['vendor_id'],
                    'location_id' => $data['location_id'],
                    'initial_quantity' => $data['initial_quantity'],
                    'current_quantity' => $data['current_quantity'],
                    'po_number' => $data['po_number'],
                    'date_of_receipt' => Carbon::createFromFormat('d/m/Y', $data['date_of_receipt']),
                    'unit_price' => $data['unit_price'],
                    'image_url' => $destinationPath,
                    'burden' => $data['burden'],
                    'project_id' => $data['project_id'],
                ]
            );
        }
    }
}
