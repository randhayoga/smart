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
        // CONFIGURATION 1: ACTUAL DATA + CONSUMABLE DUMMY DATA
        // Active by default for development. Combines actual assets with consumable data.
        // =========================================================================
        $this->call([
            RoleAndPermissionSeeder::class,
            ActualDatabaseSeeder::class,
            UserSeeder::class,
            TbProjectSeeder::class,
            TbAssignProjectSeeder::class,
            DummyConsumableDatabaseSeeder::class,
        ]);

        // =========================================================================
        // CONFIGURATION 2: PURE DUMMY DEVELOPMENT DATA (Mutually exclusive with Config 1)
        // Uncomment this entire block (and comment Config 1) to use mock inventory only.
        // =========================================================================
        // $this->call([
        //     RoleAndPermissionSeeder::class,
        //     DummyMasterSeeder::class,
        //     UserSeeder::class,
        //     TbProjectSeeder::class,
        //     TbAssignProjectSeeder::class,
        //     ModelSeeder::class,
        //     LotSeeder::class,
        //     AssetSeeder::class,
        // ]);
    }
}
