<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('barangs', 'nama')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->renameColumn('nama', 'name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('barangs', 'name')) {
            Schema::table('barangs', function (Blueprint $table) {
                $table->renameColumn('name', 'nama');
            });
        }
    }
};
