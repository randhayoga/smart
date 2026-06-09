<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            BarangSeeder::class,
            LotSeeder::class,
            UnitSeeder::class,
            UnitStatusApprovalSeeder::class,
        ]);
    }
}
