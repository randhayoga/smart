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
        if (app()->isProduction()) {
            throw new \RuntimeException('Database seeding is strictly prohibited in production to protect SMART and external databases (USER_HRIS, RE_PORTALDB).');
        }

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
