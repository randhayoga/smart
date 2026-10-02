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
        Schema::table('units', function (Blueprint $table) {
            $table->string('status')->default('Belum Diverifikasi')->change();
            $table->string('condition')->default(null)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('status')->default('Tidak Aktif')->change();
            $table->string('condition')->default('Belum Diverifikasi')->change();
        });
    }
};
