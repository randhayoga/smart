<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TbProject;

class TbProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->warn('Seeding skipped: TbProject resides in external database (RE_PORTALDB).');
        return;

        TbProject::factory()->count(2)->create();
    }
}
