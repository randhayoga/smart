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
        // CONFIGURATION 1 (Showcase)
        // Combines production baseline with showcase dummy data
        // (manual request, maintenance, QC, input, activation, and deactivation).
        // =========================================================================
        $this->call([
            RoleAndPermissionSeeder::class,
            ActualDatabaseSeeder::class,
            DummyConsumableDatabaseSeeder::class,
            DummyRequestShowcaseSeeder::class,
            DummyMaintenanceQcSeeder::class,
            DummyNonConsumableShowcaseSeeder::class,
            DummySoftwareShowcaseSeeder::class,
        ]);

        // =========================================================================
        // CONFIGURATION 2 (Dev: Showcase + Dummy Users for Safe Testing)
        // Includes showcase dummy data plus local dev users and dummy external projects.
        // Uncomment this block (and comment Configuration 1) for safe testing in local dev.
        // =========================================================================
        // $this->call([
        //     RoleAndPermissionSeeder::class,
        //     ActualDatabaseSeeder::class,
        //     UserSeeder::class,
        //     TbProjectSeeder::class,
        //     TbAssignProjectSeeder::class,
        //     DummyConsumableDatabaseSeeder::class,
        //     DummyRequestShowcaseSeeder::class,
        //     DummyMaintenanceQcSeeder::class,
        //     DummyNonConsumableShowcaseSeeder::class,
        //     DummySoftwareShowcaseSeeder::class,
        // ]);
    }
}
