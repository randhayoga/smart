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
        TbProject::factory()->count(5)->create();
    }
}
