<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummyConsumableDatabaseSeeder extends Seeder
{
    /**
     * Run the consumable dummy seeds.
     * Order:
     * 1. Master Data (ATK Category, Subcategories, Brands, Vendors, UOMs)
     * 2. Catalog Data (Consumable Barangs)
     * 3. Inventory Data (Consumable Lots)
     */
    public function run(): void
    {
        $this->call([
            DummyConsumableMasterSeeder::class,
            DummyConsumableBarangSeeder::class,
            DummyConsumableLotSeeder::class,
        ]);
    }
}
