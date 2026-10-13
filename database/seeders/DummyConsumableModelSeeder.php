<?php

namespace Database\Seeders;

use App\Models\Inventory\Model;
use App\Models\Master\Brand;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DummyConsumableModelSeeder extends Seeder
{
    /**
     * Run the consumable dummy Model database seeds.
     */
    public function run(): void
    {
        $hvsType = Type::where('code', 'ATK-HVS4')->first();
        $penType = Type::where('code', 'ATK-PLPH')->first();

        $siduBrand = Brand::where('name', 'Sinar Dunia')->first();
        $snowmanBrand = Brand::where('name', 'Snowman')->first();
        $standardBrand = Brand::where('name', 'Standard')->first();

        $rimUom = Uom::where('name', 'Rim')->first();
        $buahUom = Uom::where('name', 'Buah')->first();

        $models = [
            [
                'number' => 'ATK-HVS4-0001',
                'type_id' => $hvsType?->id,
                'brand_id' => $siduBrand?->id,
                'uom_id' => $rimUom?->id,
                'name' => 'Kertas HVS A4',
                'specification' => '75 GSM',
                'min_stock_threshold' => 10,
                'image_url' => 'database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'ATK-PLPH-0001',
                'type_id' => $penType?->id,
                'brand_id' => $snowmanBrand?->id,
                'uom_id' => $buahUom?->id,
                'name' => 'Pulpen Hitam',
                'specification' => 'v5 0,7',
                'min_stock_threshold' => 25,
                'image_url' => 'database/seeders/assets/snowman.jpg',
            ],
            [
                'number' => 'ATK-PLPH-0002',
                'type_id' => $penType?->id,
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

        foreach ($models as $data) {
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

            Model::updateOrCreate(
                ['number' => $data['number']],
                [
                    'type_id' => $data['type_id'],
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
