<?php

namespace Database\Seeders;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Room;
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
        // 1. Categories
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

        // 2. Subcategories
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

        // 3. Uoms
        $uoms = ['Unit', 'Rim', 'Buah'];
        foreach ($uoms as $uomName) {
            Uom::create(['name' => $uomName]);
        }

        // 4. Brands
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

        // 5. Organizers
        $organizers = ['CFS', 'ICT', 'HSE'];
        foreach ($organizers as $orgName) {
            Organizer::create(['name' => $orgName]);
        }

        // 6. Vendors (using 5 believable Indonesian PT names)
        $vendors = [
            'PT Surya Abadi Mandiri',
            'PT Jaya Sentosa Sejahtera',
            'PT Media Pratama Nusantara',
            'PT Mitra Global Solusindo',
            'PT Karya Indah Semesta',
        ];
        foreach ($vendors as $vendorName) {
            Vendor::create([
                'name' => $vendorName,
                'address' => 'Jl. Jenderal Sudirman No. ' . rand(1, 100) . ', Jakarta',
                'phone_number' => '021-' . rand(5000000, 9999999),
                'email' => strtolower(str_replace(' ', '', $vendorName)) . '@example.com',
                'description' => 'Supplier untuk ' . $vendorName,
                'contact_person_1' => 'Budi Santoso',
                'cp_email_1' => 'budi.santoso@example.com',
                'cp_phone_1' => '0812' . rand(10000000, 99999999),
            ]);
        }

        // 7. Locations
        $locations = ['Graha RE 1', 'Site A'];
        $locationModels = [];
        foreach ($locations as $locName) {
            $locationModels[$locName] = Location::create(['name' => $locName]);
        }

        // 8. Floors
        $floors = [
            [
                'name' => 'Lantai Mezzanine',
                'location_key' => 'Graha RE 1',
            ],
            [
                'name' => 'Lantai 4',
                'location_key' => 'Graha RE 1',
            ],
        ];
        $floorModels = [];
        foreach ($floors as $fl) {
            $floorModels[$fl['name']] = Floor::create([
                'name' => $fl['name'],
                'location_id' => $locationModels[$fl['location_key']]->id,
            ]);
        }

        // 9. Rooms
        $rooms = [
            [
                'name' => 'Ruang IFS Departemen',
                'floor_key' => 'Lantai Mezzanine',
            ],
            [
                'name' => 'Ruang Mega Mendung',
                'floor_key' => 'Lantai 4',
            ],
        ];
        foreach ($rooms as $rm) {
            Room::create([
                'name' => $rm['name'],
                'floor_id' => $floorModels[$rm['floor_key']]->id,
            ]);
        }
    }
}
