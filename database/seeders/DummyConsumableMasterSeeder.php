<?php

namespace Database\Seeders;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use Illuminate\Database\Seeder;

class DummyConsumableMasterSeeder extends Seeder
{
    /**
     * Run the consumable dummy master database seeds.
     */
    public function run(): void
    {
        // 1. Consumable Category: ATK (Alat Tulis Kantor)
        $category = Category::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis Kantor']
        );

        // 2. Consumable Subcategories
        $subcategories = [
            [
                'code' => 'ATK-HVS4',
                'name' => 'Kertas HVS A4',
                'description' => 'Kertas HVS Ukuran A4',
                'is_consumable' => true,
            ],
            [
                'code' => 'ATK-PLPH',
                'name' => 'Pulpen Hitam',
                'description' => 'Pulpen Tinta Hitam',
                'is_consumable' => true,
            ],
        ];

        foreach ($subcategories as $sub) {
            Subcategory::firstOrCreate(
                ['code' => $sub['code']],
                [
                    'name' => $sub['name'],
                    'description' => $sub['description'],
                    'category_id' => $category->id,
                    'is_consumable' => $sub['is_consumable'],
                ]
            );
        }

        // 3. Units of Measure
        $uoms = ['Rim', 'Buah', 'Unit', 'Pak', 'Dus'];
        foreach ($uoms as $uomName) {
            Uom::firstOrCreate(['name' => $uomName]);
        }

        // 4. Organizers
        $organizers = ['CFS', 'ICT', 'HSE'];
        foreach ($organizers as $orgName) {
            \App\Models\Master\Organizer::firstOrCreate(['name' => $orgName]);
        }

        // 5. Locations (Ensures at least one storage location exists for consumables)
        $graha = \App\Models\Master\Location::firstOrCreate(
            ['name' => 'Graha RE 1', 'parent_id' => null],
            ['is_active' => true]
        );
        $mezzanine = \App\Models\Master\Location::firstOrCreate(
            ['name' => 'Lantai Mezzanine', 'parent_id' => $graha->id],
            ['is_active' => true]
        );
        \App\Models\Master\Location::firstOrCreate(
            ['name' => 'Ruang IFS Departemen', 'parent_id' => $mezzanine->id],
            ['is_active' => true]
        );

        // 4. Consumable Brands
        $brands = [
            'Sinar Dunia',
            'Paper One',
            'Snowman',
            'Standard',
        ];
        foreach ($brands as $brandName) {
            Brand::firstOrCreate(['name' => $brandName]);
        }

        // 5. Consumable Vendors / Suppliers
        $vendors = [
            [
                'name' => 'PT Surya Abadi Mandiri',
                'code' => 'VN-SAM1',
                'address' => 'Jl. Jenderal Sudirman No. 45, Jakarta',
                'phone_number' => '081234567890',
                'email' => 'sales@suryaabadi.com',
                'description' => 'Supplier ATK dan Kertas Kantor',
                'contact_person_1' => 'Budi Santoso',
                'cp_email_1' => 'budi.santoso@suryaabadi.com',
                'cp_phone_1' => '081234567891',
            ],
            [
                'name' => 'PT Mitra Global Solusindo',
                'code' => 'VN-MGS1',
                'address' => 'Jl. Gatot Subroto No. 12, Jakarta',
                'phone_number' => '081298765432',
                'email' => 'contact@mitraglobal.com',
                'description' => 'Distributor Alat Kantor & Perlengkapan Kerja',
                'contact_person_1' => 'Dewi Lestari',
                'cp_email_1' => 'dewi.lestari@mitraglobal.com',
                'cp_phone_1' => '081298765433',
            ],
        ];

        foreach ($vendors as $vendorData) {
            Vendor::firstOrCreate(
                ['name' => $vendorData['name']],
                $vendorData
            );
        }
    }
}
