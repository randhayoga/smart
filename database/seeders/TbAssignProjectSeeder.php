<?php

namespace Database\Seeders;

use App\Models\TbAssignProject;
use App\Models\TbProject;
use Illuminate\Database\Seeder;

class TbAssignProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = TbProject::first();

        if (! $project) {
            return;
        }

        TbAssignProject::create([
            'npk' => '121212',
            'no_project' => $project->no_project,
            'id_rbs' => 'P0',
            'start_date' => '2026-01-01 00:00:00',
        ]);

        TbAssignProject::create([
            'npk' => '090909',
            'no_project' => $project->no_project,
            'id_rbs' => 'P2211', // Project Manager
            // 'id_rbs' => 'P2231', // Project Controller
            'start_date' => '2026-01-01 00:00:00',
        ]);
    }
}
