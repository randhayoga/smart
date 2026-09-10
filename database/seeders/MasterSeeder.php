<?php

namespace Database\Seeders;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'ATK' => [
                'name' => 'Alat Tulis Kantor',
                'is_consumable' => true,
            ],
            'FUR' => [
                'name' => 'Furnitur',
                'is_consumable' => false,
            ],
            'COMP' => [
                'name' => 'Computer',
                'is_consumable' => false,
            ],
            'MON' => [
                'name' => 'Monitor',
                'is_consumable' => false,
            ],
            'KEN' => [
                'name' => 'Kendaraan',
                'is_consumable' => false,
            ],
        ];
        $categoryModels = [];
        foreach ($categories as $code => $data) {
            $categoryModels[$code] = Category::create([
                'code' => $code,
                'name' => $data['name'],
                'is_consumable' => $data['is_consumable'],
            ]);
        }

        $subcategories = [
            [
                'code' => 'ATK-HVS4',
                'name' => 'Kertas HVS A4',
                'category_code' => 'ATK',
            ],
            [
                'code' => 'ATK-PLPH',
                'name' => 'Pulpen Hitam',
                'category_code' => 'ATK',
            ],
            [
                'code' => 'FUR-KK',
                'name' => 'Kursi Kerja',
                'description' => 'Kerja, Kerja, Kerja',
                'category_code' => 'FUR',
            ],
            [
                'code' => 'FUR-MK',
                'name' => 'Meja Kerja',
                'category_code' => 'FUR',
            ],
            [
                'code' => 'COMP-NB',
                'name' => 'Notebook',
                'category_code' => 'COMP',
            ],
            [
                'code' => 'MON-LCD',
                'name' => 'LCD',
                'category_code' => 'MON',
            ],
            [
                'code' => 'KEN-MO',
                'name' => 'Mobil',
                'category_code' => 'KEN',
            ],
        ];
        foreach ($subcategories as $sub) {
            Subcategory::create([
                'code' => $sub['code'],
                'name' => $sub['name'],
                'description' => $sub['description'] ?? null,
                'category_id' => $categoryModels[$sub['category_code']]->id,
            ]);
        }

        $uoms = ['Unit', 'Rim', 'Buah'];
        foreach ($uoms as $uomName) {
            Uom::create(['name' => $uomName]);
        }

        $brands = [
            'HP',
            'Lenovo',
            'Acer',
            'Dell',
            'Sinar Dunia',
            'Paper One',
            'Snowman',
            'Standard',
            'IKEA',
            'Informa',
            'Toyota',
            'BYD',
        ];
        foreach ($brands as $brandName) {
            Brand::create([
                'name' => $brandName,
            ]);
        }

        $organizers = ['CFS', 'ICT', 'HSE'];
        foreach ($organizers as $orgName) {
            Organizer::create(['name' => $orgName]);
        }

        $vendors = [
            'PT Surya Abadi Mandiri',
            'PT Jaya Sentosa Sejahtera',
            'PT Media Pratama Nusantara',
            'PT Mitra Global Solusindo',
            'PT Karya Indah Semesta',
        ];
        $vendorCodes = ['VN0001', 'VN0002', 'VN0003', 'VN0004', 'VN0005'];
        foreach ($vendors as $index => $vendorName) {
            Vendor::create([
                'code' => $vendorCodes[$index],
                'name' => $vendorName,
                'address' => 'Jl. Jenderal Sudirman No. ' . rand(1, 100) . ', Jakarta',
                'phone_number' => '08' . rand(5000000, 9999999),
                'email' => strtolower(str_replace(' ', '', $vendorName)) . '@example.com',
                'description' => 'Supplier untuk ' . $vendorName,
                'contact_person_1' => 'Budi Santoso',
                'cp_email_1' => 'budi.santoso@example.com',
                'cp_phone_1' => '0812' . rand(10000000, 99999999),
            ]);
        }

        // 1: Graha RE 1 (Root)
        $graha = Location::create([
            'name' => 'Graha RE 1',
            'parent_id' => null,
            'is_active' => true,
        ]);

        // 2: Lantai Mezzanine (Child of Graha RE 1)
        $mezzanine = Location::create([
            'name' => 'Lantai Mezzanine',
            'parent_id' => $graha->id,
            'is_active' => true,
        ]);

        // 3: Ruang IFS Departemen (Child of Lantai Mezzanine)
        $ruangIfs = Location::create([
            'name' => 'Ruang IFS Departemen',
            'parent_id' => $mezzanine->id,
            'is_active' => true,
        ]);

        // 4: Lantai 4 (Child of Graha RE 1)
        $lantai4 = Location::create([
            'name' => 'Lantai 4',
            'parent_id' => $graha->id,
            'is_active' => true,
        ]);

        // 5: Ruang Mega Mendung (Child of Lantai 4)
        $ruangMega = Location::create([
            'name' => 'Ruang Mega Mendung',
            'parent_id' => $lantai4->id,
            'is_active' => true,
        ]);

        // 6: Site A (Root)
        Location::create([
            'name' => 'Site A',
            'parent_id' => null,
            'is_active' => true,
        ]);
    }
}
