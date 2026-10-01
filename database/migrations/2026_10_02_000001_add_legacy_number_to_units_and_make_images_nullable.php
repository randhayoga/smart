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
        Schema::table('barangs', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });

        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'legacy_number')) {
                $table->string('legacy_number', 50)->nullable()->after('number')->comment('legacy asset code/number');
            }
            $table->string('image_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'legacy_number')) {
                $table->dropColumn('legacy_number');
            }
            $table->string('image_url')->nullable(false)->change();
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->string('image_url')->nullable(false)->change();
        });

        Schema::table('barangs', function (Blueprint $table) {
            $table->string('image_url')->nullable(false)->change();
        });
    }
};
