<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations to add performance indexes for frequently filtered and sorted columns.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->index('status');
            $table->index('condition');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('unit_lifecycles', function (Blueprint $table) {
            $table->index('start_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['condition']);
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('unit_lifecycles', function (Blueprint $table) {
            $table->dropIndex(['start_date']);
            $table->dropIndex(['status']);
        });
    }
};
