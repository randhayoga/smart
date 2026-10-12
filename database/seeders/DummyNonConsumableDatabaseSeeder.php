<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummyNonConsumableDatabaseSeeder extends Seeder
{
    /**
     * Run the full non-consumable dummy database seeds.
     * Order:
     * 1. Master Data (Categories, Subcategories, Brands, Vendors, Organizers, Locations)
     * 2. Catalog Data (Barangs)
     * 3. Inventory Lots
     * 4. Units
     */
    public function run(): void
    {
        $this->call([
            DummyNonConsumableMasterSeeder::class,
            DummyNonConsumableBarangSeeder::class,
            DummyNonConsumableLotSeeder::class,
            DummyNonConsumableUnitSeeder::class,
        ]);
    }
}
