<?php

namespace Database\Seeders;

use App\Models\HrdOrgchart;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use Illuminate\Database\Seeder;

class DummyMasterSeeder extends Seeder
{
    /**
     * Run the development dummy master database seeds.
     * Delegates to both consumable and non-consumable master seeders.
     */
    public function run(): void
    {
        $this->call([
            DummyConsumableMasterSeeder::class,
            DummyNonConsumableMasterSeeder::class,
        ]);
    }
}
