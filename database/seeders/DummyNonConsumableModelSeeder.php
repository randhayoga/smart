<?php

namespace Database\Seeders;

use App\Models\Inventory\Model;
use App\Models\Master\Brand;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DummyNonConsumableModelSeeder extends Seeder
{
    /**
     * Run the non-consumable dummy Model database seeds.
     */
    public function run(): void
    {
        $furKk = Type::where('code', 'FUR-KK')->first();
        $furMk = Type::where('code', 'FUR-MK')->first();
        $compNb = Type::where('code', 'COMP-NB')->first();
        $kenMo = Type::where('code', 'KEN-MO')->first();

        $ikea = Brand::where('name', 'IKEA')->first();
        $informa = Brand::where('name', 'Informa')->first();
        $acer = Brand::where('name', 'Acer')->first();
        $byd = Brand::where('name', 'BYD')->first();
        $toyota = Brand::where('name', 'Toyota')->first();

        $unitUom = Uom::where('name', 'Unit')->first() ?? Uom::first();
        $buahUom = Uom::where('name', 'Buah')->first() ?? $unitUom;

        $models = [
            [
                'number' => 'FUR-KK-0001',
                'type_id' => $furKk?->id,
                'brand_id' => $ikea?->id,
                'uom_id' => $buahUom?->id,
                'name' => 'Kursi Kantor FLINTAN',
                'image_url' => 'database/seeders/assets/ikea.jpg',
            ],
            [
                'number' => 'FUR-MK-0001',
                'type_id' => $furMk?->id,
                'brand_id' => $informa?->id,
                'uom_id' => $buahUom?->id,
                'name' => 'Meja Kerja Halley',
                'image_url' => 'database/seeders/assets/informa.jpg',
            ],
            [
                'number' => 'COMP-NB-0001',
                'type_id' => $compNb?->id,
                'brand_id' => $acer?->id,
                'uom_id' => $buahUom?->id,
                'name' => 'Swift Go SFG14',
                'specification' => null,
                'image_url' => 'database/seeders/assets/acer.jpg',
            ],
            [
                'number' => 'KEN-MO-0001',
                'type_id' => $kenMo?->id,
                'brand_id' => $byd?->id,
                'uom_id' => $unitUom?->id,
                'name' => 'M6 EV',
                'specification' => 'Superior',
                'image_url' => 'database/seeders/assets/byd.jpeg',
            ],
            [
                'number' => 'KEN-MO-0002',
                'type_id' => $kenMo?->id,
                'brand_id' => $toyota?->id,
                'uom_id' => $unitUom?->id,
                'name' => 'Innova Zenix Hybrid',
                'specification' => 'Q Modellista',
                'image_url' => 'database/seeders/assets/zenix.jpeg',
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
            if (!$data['type_id']) {
                continue;
            }

            $sourcePath = base_path(ltrim($data['image_url'], '/'));
            $destinationPath = 'inventory/' . basename($sourcePath);

            if (File::exists($sourcePath)) {
                if (!Storage::disk('local')->exists('inventory')) {
                    Storage::disk('local')->makeDirectory('inventory');
                }
                Storage::disk('local')->put($destinationPath, File::get($sourcePath));
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
                ]
            );
        }
    }
}
