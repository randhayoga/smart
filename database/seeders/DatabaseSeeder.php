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
            $this->command?->warn('Production environment detected: Skipping development and external dummy seeders.');
            $this->call(RoleAndPermissionSeeder::class);
            return;
        }

        $this->call([
            MasterSeeder::class,
            UserSeeder::class,
            RoleAndPermissionSeeder::class,
            TbProjectSeeder::class,
            TbAssignProjectSeeder::class,
            BarangSeeder::class,
            LotSeeder::class,
            UnitSeeder::class,
        ]);
    }
}
