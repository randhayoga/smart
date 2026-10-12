<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Lot;
use Illuminate\Support\Facades\Storage;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Consumables do not have units, so this delegates to DummyNonConsumableUnitSeeder.
     */
    public function run(): void
    {
        $this->call([
            DummyNonConsumableUnitSeeder::class,
        ]);
    }
}
