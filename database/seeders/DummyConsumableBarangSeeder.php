<?php

namespace Database\Seeders;

use App\Models\Inventory\Barang;
use App\Models\Master\Brand;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DummyConsumableBarangSeeder extends Seeder
{
    /**
     * Run the consumable dummy Barang database seeds.
     */
    public function run(): void
    {
        $hvsSubcat = Subcategory::where('code', 'ATK-HVS4')->first();
        $penSubcat = Subcategory::where('code', 'ATK-PLPH')->first();

        $siduBrand = Brand::where('name', 'Sinar Dunia')->first();
        $snowmanBrand = Brand::where('name', 'Snowman')->first();
        $standardBrand = Brand::where('name', 'Standard')->first();

        $rimUom = Uom::where('name', 'Rim')->first();
        $buahUom = Uom::where('name', 'Buah')->first();

        $barangs = [
            [
                'number' => 'ATK-HVS4-0001',
                'subcategory_id' => $hvsSubcat?->id,
                'brand_id' => $siduBrand?->id,
                'uom_id' => $rimUom?->id,
                'name' => 'Kertas HVS A4',
                'specification' => '75 GSM',
                'min_stock_threshold' => 10,
                'image_url' => 'database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'ATK-PLPH-0001',
                'subcategory_id' => $penSubcat?->id,
                'brand_id' => $snowmanBrand?->id,
                'uom_id' => $buahUom?->id,
                'name' => 'Pulpen Hitam',
                'specification' => 'v5 0,7',
                'min_stock_threshold' => 25,
                'image_url' => 'database/seeders/assets/snowman.jpg',
            ],
            [
                'number' => 'ATK-PLPH-0002',
                'subcategory_id' => $penSubcat?->id,
                'brand_id' => $standardBrand?->id,
                'uom_id' => $buahUom?->id,
                'name' => 'Pulpen Hitam',
                'specification' => 'AE7 0,5',
                'min_stock_threshold' => 25,
                'image_url' => 'database/seeders/assets/standard.jpg',
            ],
        ];

        $placeholderSource = base_path('database/seeders/assets/placeholder.jpg');
        if (File::exists($placeholderSource)) {
            if (!Storage::disk('local')->exists('inventory')) {
                Storage::disk('local')->makeDirectory('inventory');
            }
            Storage::disk('local')->put('inventory/placeholder.jpg', File::get($placeholderSource));
        }

        foreach ($barangs as $data) {
            $destinationPath = null;
            if (!empty($data['image_url'])) {
                $sourcePath = base_path(ltrim($data['image_url'], '/'));
                if (File::exists($sourcePath)) {
                    $destinationPath = 'inventory/' . basename($sourcePath);
                    if (!Storage::disk('local')->exists('inventory')) {
                        Storage::disk('local')->makeDirectory('inventory');
                    }
                    Storage::disk('local')->put($destinationPath, File::get($sourcePath));
                }
            }

            Barang::updateOrCreate(
                ['number' => $data['number']],
                [
                    'subcategory_id' => $data['subcategory_id'],
                    'brand_id' => $data['brand_id'],
                    'uom_id' => $data['uom_id'],
                    'name' => $data['name'],
                    'specification' => $data['specification'] ?? null,
                    'min_stock_threshold' => $data['min_stock_threshold'] ?? null,
                    'image_url' => $destinationPath,
                ]
            );
        }
    }
}
