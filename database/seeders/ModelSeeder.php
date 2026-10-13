<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Delegates to both consumable and non-consumable dummy model seeders.
     */
    public function run(): void
    {
        $this->call([
            DummyConsumableModelSeeder::class,
            DummyNonConsumableModelSeeder::class,
        ]);
    }
}
