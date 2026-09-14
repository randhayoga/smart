<?php

namespace Database\Seeders;

use App\Models\TbAssignProject;
use App\Models\TbProject;
use Illuminate\Database\Seeder;

class TbProjectSeeder extends Seeder
{
    /**
     * Deterministic fake project codes.
     */
    public const FAKE_PROJECT_CODES = [
        '99-9901',
        '99-9902',
    ];

    /**
     * Fake project data definitions.
     */
    public const FAKE_PROJECTS = [
        [
            'no_project' => '99-9901',
            'project_name' => 'Proyek Fasilitas Internal (Fake)',
            'client_id' => '1',
        ],
        [
            'no_project' => '99-9902',
            'project_name' => 'Proyek Konstruksi Klien (Fake)',
            'client_id' => '2',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Safe cleanup: remove only assignments on fake projects and fake projects themselves
        TbAssignProject::whereIn('no_project', self::FAKE_PROJECT_CODES)->delete();
        TbProject::whereIn('no_project', self::FAKE_PROJECT_CODES)->delete();

        // 2. Create the deterministic fake projects
        foreach (self::FAKE_PROJECTS as $projectData) {
            TbProject::create($projectData);
        }
    }
}
