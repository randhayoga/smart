<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\Lot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Delegates to both consumable and non-consumable dummy lot seeders.
     */
    public function run(): void
    {
        $this->call([
            DummyConsumableLotSeeder::class,
            DummyNonConsumableLotSeeder::class,
        ]);
    }
}
