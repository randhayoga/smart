<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill any existing null rows before enforcing NOT NULL
        DB::table('units')->whereNull('type')->update(['type' => 'LT']);
        DB::table('units')->whereNull('classification')->update(['classification' => 'Aset']);

        Schema::table('units', function (Blueprint $table) {
            $table->string('type', 2)->nullable(false)->change();
            $table->string('classification', 255)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('type', 2)->nullable()->change();
            $table->string('classification', 255)->nullable()->change();
        });
    }
};
