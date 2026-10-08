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
            $this->call(ActualDatabaseSeeder::class);
            return;
        }

        // =========================================================================
        // CONFIGURATION 1: ACTUAL / FINAL DATA (+ UOM & ORGANIZER DUMMY DATA)
        // Active by default. Comment this entire block when switching to dummy data.
        // =========================================================================
        // $this->call([
        //     RoleAndPermissionSeeder::class,
        //     ActualMasterSeeder::class,     // Categories, Subcategories, Brands, Vendors, Locations
        //     UserSeeder::class,
        //     TbProjectSeeder::class,
        //     TbAssignProjectSeeder::class,
        // ]);

        // =========================================================================
        // CONFIGURATION 2: DUMMY DEVELOPMENT DATA (Mutually exclusive with Config 1)
        // Uncomment this entire block (and comment Config 1) to use mock inventory.
        // =========================================================================
        // $this->call([
        //     RoleAndPermissionSeeder::class,
        //     DummyMasterSeeder::class,
        //     UserSeeder::class,
        //     TbProjectSeeder::class,
        //     TbAssignProjectSeeder::class,
        //     BarangSeeder::class,
        //     LotSeeder::class,
        //     UnitSeeder::class,
        // ]);
    }
}
