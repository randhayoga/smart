<?php

namespace Database\Seeders;

use App\Models\TbRbs;
use Illuminate\Database\Seeder;

class TbRbsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TbRbs::create([
            'id' => 'P0',
            'name' => 'Anggota',
            'showing_name' => 'Anggota',
        ]);

        TbRbs::create([
            'id' => 'P2211',
            'name' => 'Project Manager',
            'showing_name' => 'Project Manager',
        ]);
    }
}
