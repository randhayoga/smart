<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\Barang;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BarangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $barangs = [
            [
                'number' => 'ATK-KER-0001',
                'subcategory_id' => 1,
                'brand_id' => 5,
                'uom_id' => 2,
                'name' => 'Kertas HVS A4',
                'specification' => '75 GSM',
                'image_url' => 'database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'ATK-PUL-0001',
                'subcategory_id' => 2,
                'brand_id' => 7,
                'uom_id' => 3,
                'name' => 'Pulpen Hitam',
                'specification' => 'v5 0,7',
                'image_url' => 'database/seeders/assets/snowman.jpg',
            ],
            [
                'number' => 'ATK-PUL-0002',
                'subcategory_id' => 2,
                'brand_id' => 8,
                'uom_id' => 3,
                'name' => 'Pulpen Hitam',
                'specification' => 'AE7 0,5',
                'image_url' => 'database/seeders/assets/standard.jpg',
            ],
            [
                'number' => 'FUR-KUR-0001',
                'subcategory_id' => 3,
                'brand_id' => 9,
                'uom_id' => 3,
                'name' => 'Kursi Kantor FLINTAN',
                'image_url' => 'database/seeders/assets/ikea.jpg',
            ],
            [
                'number' => 'FUR-MEJ-0001',
                'subcategory_id' => 4,
                'brand_id' => 10,
                'uom_id' => 3,
                'name' => 'Meja Kerja Halley',
                'image_url' => 'database/seeders/assets/informa.jpg',
            ],
            [
                'number' => 'ELE-LAP-0001',
                'subcategory_id' => 5,
                'brand_id' => 3,
                'uom_id' => 3,
                'name' => 'Acer Swift Go SFG14',
                'specification' => 'Ultra 5 32GB 512GB',
                'image_url' => 'database/seeders/assets/acer.jpg',
            ],
            [
                'number' => 'KEN-MOB-0001',
                'subcategory_id' => 7,
                'brand_id' => 12,
                'uom_id' => 1,
                'name' => 'BYD M6 EV',
                'specification' => 'Superior',
                'image_url' => 'database/seeders/assets/byd.jpeg',
            ],
            [
                'number' => 'KEN-MOB-0002',
                'subcategory_id' => 7,
                'brand_id' => 11,
                'uom_id' => 1,
                'name' => 'Innova Zenix Hybrid',
                'specification' => 'Q Modellista',
                'image_url' => 'database/seeders/assets/zenix.jpeg',
            ],
        ];

        foreach ($barangs as $data) {
            $sourcePath = base_path(ltrim($data['image_url'], '/'));

            $destinationPath = 'inventory/' . basename($sourcePath);
            
            if (File::exists($sourcePath)) {
                // Copy to public storage
                if (!Storage::disk('public')->exists('inventory')) {
                    Storage::disk('public')->makeDirectory('inventory');
                }
                Storage::disk('public')->put($destinationPath, File::get($sourcePath));
            }

            Barang::updateOrCreate(
                ['number' => $data['number']],
                [
                    'subcategory_id' => $data['subcategory_id'],
                    'brand_id' => $data['brand_id'],
                    'uom_id' => $data['uom_id'],
                    'name' => $data['name'],
                    'specification' => $data['specification'] ?? null,
                    'image_url' => $destinationPath,
                ]
            );
        }
    }
}
