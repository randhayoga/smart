<?php

namespace Database\Seeders;

use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\TbRbs;
use Illuminate\Database\Seeder;

class TbAssignProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allFakeNpks = array_values(array_unique(array_merge(
            UserSeeder::FAKE_USERNAMES,
            UserSeeder::LEGACY_FAKE_USERNAMES
        )));

        // 1. Safe cleanup: remove only assignments involving fake users or fake projects
        TbAssignProject::whereIn('npk', $allFakeNpks)
            ->orWhereIn('no_project', TbProjectSeeder::FAKE_PROJECT_CODES)
            ->delete();

        // 2. Find the primary fake project
        $project = TbProject::where('no_project', '99-9901')->first()
            ?? TbProject::whereIn('no_project', TbProjectSeeder::FAKE_PROJECT_CODES)->first();

        if (! $project) {
            $this->command?->warn('TbAssignProjectSeeder: Fake project 99-9901 not found. Please run TbProjectSeeder first.');
            return;
        }

        // Determine appropriate member RBS code (P2223 for Engineer in RE_PORTALDB, or P0 in testing)
        $memberRbs = TbRbs::where('id', 'P2223')->exists() ? 'P2223' : 'P0';

        // 3. Assign regular user (999997) as team member
        TbAssignProject::create([
            'npk' => '999997',
            'no_project' => $project->no_project,
            'id_rbs' => $memberRbs,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        // 4. Assign project manager (999995) as Project Manager (P2211)
        TbAssignProject::create([
            'npk' => '999995',
            'no_project' => $project->no_project,
            'id_rbs' => 'P2211',
            'start_date' => '2026-01-01 00:00:00',
        ]);
    }
}
