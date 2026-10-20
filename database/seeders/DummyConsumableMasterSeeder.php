<?php

namespace Database\Seeders;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Type;
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

        // 2. Consumable Types
        $types = [
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

        foreach ($types as $type) {
            Type::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'category_id' => $category->id,
                    'is_consumable' => $type['is_consumable'],
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
        $maxVendorNumber = 0;
        $existingVendors = Vendor::where('code', 'like', 'VN%')->get(['code']);
        foreach ($existingVendors as $ev) {
            if (preg_match('/^VN(\d+)$/', $ev->code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxVendorNumber) {
                    $maxVendorNumber = $num;
                }
            }
        }

        $vendors = [
            [
                'name' => 'PT Surya Abadi Mandiri',
                'address' => 'Jl. Jenderal Sudirman No. 45, Jakarta',
                'phone_number' => '081234567890',
                'email' => 'sales@suryaabadi.com',
                'description' => 'Supplier ATK dan Kertas Kantor',
                'contact_persons' => [
                    [
                        'contact_person' => 'Budi Santoso',
                        'cp_email' => 'budi.santoso@suryaabadi.com',
                        'cp_phone' => '081234567891',
                    ],
                ],
            ],
            [
                'name' => 'PT Mitra Global Solusindo',
                'address' => 'Jl. Gatot Subroto No. 12, Jakarta',
                'phone_number' => '081298765432',
                'email' => 'contact@mitraglobal.com',
                'description' => 'Distributor Alat Kantor & Perlengkapan Kerja',
                'contact_persons' => [
                    [
                        'contact_person' => 'Dewi Lestari',
                        'cp_email' => 'dewi.lestari@mitraglobal.com',
                        'cp_phone' => '081298765433',
                    ],
                ],
            ],
        ];

        foreach ($vendors as $vendorData) {
            $cps = $vendorData['contact_persons'] ?? [];
            unset($vendorData['contact_persons']);

            $vendor = Vendor::where('name', $vendorData['name'])->first();
            if (! $vendor) {
                $maxVendorNumber++;
                $vendorData['code'] = 'VN' . str_pad((string) $maxVendorNumber, 5, '0', STR_PAD_LEFT);
                $vendor = Vendor::create($vendorData);
            }

            foreach ($cps as $cp) {
                $vendor->contactPersons()->firstOrCreate(
                    ['contact_person' => $cp['contact_person']],
                    $cp
                );
            }
        }
    }
}
