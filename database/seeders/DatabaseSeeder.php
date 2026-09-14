<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            MasterSeeder::class,
            UserSeeder::class,
            TbProjectSeeder::class,
            TbAssignProjectSeeder::class,
            BarangSeeder::class,
            LotSeeder::class,
            UnitSeeder::class,
        ]);
    }
}
