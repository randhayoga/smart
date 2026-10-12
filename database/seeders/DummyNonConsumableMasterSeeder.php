<?php

namespace Database\Seeders;

use App\Models\HrdOrgchart;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use Illuminate\Database\Seeder;

class DummyNonConsumableMasterSeeder extends Seeder
{
    /**
     * Run the development dummy non-consumable master database seeds.
     */
    public function run(): void
    {
        $categories = [
            'FUR' => [
                'name' => 'Furniture',
            ],
            'COMP' => [
                'name' => 'Computer',
            ],
            'MON' => [
                'name' => 'Monitor',
            ],
            'KEN' => [
                'name' => 'Kendaraan',
            ],
        ];

        $categoryModels = [];
        foreach ($categories as $code => $data) {
            $categoryModels[$code] = Category::firstOrCreate(
                ['code' => $code],
                ['name' => $data['name']]
            );
        }

        $subcategories = [
            [
                'code' => 'FUR-KK',
                'name' => 'Kursi Kerja',
                'description' => 'Kerja, Kerja, Kerja',
                'category_code' => 'FUR',
                'is_consumable' => false,
            ],
            [
                'code' => 'FUR-MK',
                'name' => 'Meja Kerja',
                'category_code' => 'FUR',
                'is_consumable' => false,
            ],
            [
                'code' => 'COMP-NB',
                'name' => 'Notebook',
                'category_code' => 'COMP',
                'is_consumable' => false,
            ],
            [
                'code' => 'MON-LCD',
                'name' => 'LCD',
                'category_code' => 'MON',
                'is_consumable' => false,
            ],
            [
                'code' => 'KEN-MO',
                'name' => 'Mobil',
                'category_code' => 'KEN',
                'is_consumable' => false,
            ],
        ];

        foreach ($subcategories as $sub) {
            $cat = $categoryModels[$sub['category_code']] ?? Category::where('code', $sub['category_code'])->first();
            if ($cat) {
                Subcategory::firstOrCreate(
                    ['code' => $sub['code']],
                    [
                        'name' => $sub['name'],
                        'description' => $sub['description'] ?? null,
                        'category_id' => $cat->id,
                        'is_consumable' => $sub['is_consumable'] ?? false,
                    ]
                );
            }
        }

        $uoms = ['Unit', 'Buah'];
        foreach ($uoms as $uomName) {
            Uom::firstOrCreate(['name' => $uomName]);
        }

        $brands = [
            'HP',
            'Lenovo',
            'Acer',
            'Dell',
            'IKEA',
            'Informa',
            'Toyota',
            'BYD',
        ];
        foreach ($brands as $brandName) {
            Brand::firstOrCreate(['name' => $brandName]);
        }

        $organizers = ['CFS', 'ICT', 'HSE'];
        foreach ($organizers as $orgName) {
            Organizer::firstOrCreate(['name' => $orgName]);
        }

        $vendors = [
            'Beragam',
            'PT Jaya Sentosa Sejahtera',
            'PT Media Pratama Nusantara',
            'PT Karya Indah Semesta',
        ];
        $vendorCodes = ['VN0000', 'VN0002', 'VN0003', 'VN0005'];
        foreach ($vendors as $index => $vendorName) {
            Vendor::firstOrCreate(
                ['name' => $vendorName],
                [
                    'code' => $vendorCodes[$index],
                    'address' => $vendorName === 'Beragam' ? null : 'Jl. Jenderal Sudirman No. ' . rand(1, 100) . ', Jakarta',
                    'phone_number' => $vendorName === 'Beragam' ? null : '08' . rand(5000000, 9999999),
                    'email' => $vendorName === 'Beragam' ? null : strtolower(str_replace(' ', '', $vendorName)) . '@example.com',
                    'description' => $vendorName === 'Beragam' ? 'Vendor penampung untuk data migrasi beragam' : 'Supplier untuk ' . $vendorName,
                    'contact_person_1' => $vendorName === 'Beragam' ? null : 'Budi Santoso',
                    'cp_email_1' => $vendorName === 'Beragam' ? null : 'budi.santoso@example.com',
                    'cp_phone_1' => $vendorName === 'Beragam' ? null : '0812' . rand(10000000, 99999999),
                ]
            );
        }

        // 1: Graha RE 1 (Root)
        $graha = Location::firstOrCreate(
            ['name' => 'Graha RE 1', 'parent_id' => null],
            ['is_active' => true]
        );

        // 2: Lantai Mezzanine (Child of Graha RE 1)
        $mezzanine = Location::firstOrCreate(
            ['name' => 'Lantai Mezzanine', 'parent_id' => $graha->id],
            ['is_active' => true]
        );

        // 3: Ruang IFS Departemen (Child of Lantai Mezzanine)
        $ifsDept = HrdOrgchart::where('org_code', 'IFS')->orWhere('org_code', 'TEST-DEPT')->first() ?? HrdOrgchart::first();
        Location::firstOrCreate(
            ['name' => 'Ruang IFS Departemen', 'parent_id' => $mezzanine->id],
            [
                'related_departement' => $ifsDept?->id,
                'is_active' => true,
            ]
        );

        // 4: Lantai 4 (Child of Graha RE 1)
        $lantai4 = Location::firstOrCreate(
            ['name' => 'Lantai 4', 'parent_id' => $graha->id],
            ['is_active' => true]
        );

        // 5: Ruang Mega Mendung (Child of Lantai 4)
        Location::firstOrCreate(
            ['name' => 'Ruang Mega Mendung', 'parent_id' => $lantai4->id],
            ['is_active' => true]
        );

        // 6: Site A (Root)
        Location::firstOrCreate(
            ['name' => 'Site A', 'parent_id' => null],
            ['is_active' => true]
        );
    }
}
