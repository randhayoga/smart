<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Consumables do not have individual assets, so this delegates to DummyNonConsumableAssetSeeder.
     */
    public function run(): void
    {
        $this->call([
            DummyNonConsumableAssetSeeder::class,
        ]);
    }
}
