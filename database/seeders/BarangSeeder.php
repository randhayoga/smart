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
                'specification' => 'HVS A4',
                'image_url' => 'database/seeders/assets/sidu.jpg',
            ],
            [
                'number' => 'ATK-PUL-0001',
                'subcategory_id' => 2,
                'brand_id' => 7,
                'uom_id' => 3,
                'specification' => 'Pulpen Hitam',
                'image_url' => 'database/seeders/assets/snowman.jpg',
            ],
            [
                'number' => 'ATK-PUL-0002',
                'subcategory_id' => 2,
                'brand_id' => 8,
                'uom_id' => 3,
                'specification' => 'Pulpen Hitam',
                'image_url' => 'database/seeders/assets/joyko.jpg',
            ],
            [
                'number' => 'FUR-KUR-0001',
                'subcategory_id' => 3,
                'brand_id' => 9,
                'uom_id' => 3,
                'specification' => 'Kursi Kantor FLINTAN',
                'image_url' => 'database/seeders/assets/ikea.jpg',
            ],
            [
                'number' => 'FUR-MEJ-0001',
                'subcategory_id' => 4,
                'brand_id' => 10,
                'uom_id' => 3,
                'specification' => 'Meja Kerja Informa Halley',
                'image_url' => 'database/seeders/assets/informa.jpg',
            ],
            [
                'number' => 'ELE-LAP-0001',
                'subcategory_id' => 5,
                'brand_id' => 3,
                'uom_id' => 3,
                'specification' => 'Swift Go SFG14 Ultra 5 32GB 512GB',
                'image_url' => 'database/seeders/assets/acer.jpg',
            ],
            [
                'number' => 'KEN-MOB-0001',
                'subcategory_id' => 7,
                'brand_id' => 12,
                'uom_id' => 1,
                'specification' => 'M6 EV Superior 7 Seater',
                'image_url' => 'database/seeders/assets/byd.jpg',
            ],
            [
                'number' => 'KEN-MOB-0002',
                'subcategory_id' => 7,
                'brand_id' => 11,
                'uom_id' => 1,
                'specification' => 'Innova Zenix Q Hybrid',
                'image_url' => 'database/seeders/assets/zenix.jpg',
            ],
        ];

        foreach ($barangs as $data) {
            // Check if the asset file exists (handling possible .jpg / .jpeg mixups)
            $sourcePath = base_path($data['image_url']);
            if (!File::exists($sourcePath)) {
                // Try .jpeg if .jpg is not found
                if (str_ends_with($sourcePath, '.jpg')) {
                    $fallback = substr($sourcePath, 0, -4) . '.jpeg';
                    if (File::exists($fallback)) {
                        $sourcePath = $fallback;
                    }
                }
                // Try standard.jpg if joyko.jpg is not found
                if (str_contains($sourcePath, 'joyko.jpg')) {
                    $fallback = str_replace('joyko.jpg', 'standard.jpg', $sourcePath);
                    if (File::exists($fallback)) {
                        $sourcePath = $fallback;
                    }
                }
            }

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
                    'specification' => $data['specification'],
                    'image_url' => $destinationPath,
                ]
            );
        }
    }
}
