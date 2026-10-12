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
     * Delegates to both consumable and non-consumable dummy barang seeders.
     */
    public function run(): void
    {
        $this->call([
            DummyConsumableBarangSeeder::class,
            DummyNonConsumableBarangSeeder::class,
        ]);
    }
}
