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
        Schema::table('request_fulfillments', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('assigned_at');
            $table->index(['unit_id', 'confirmed_at'], 'rf_unit_confirmed_idx');
            $table->index(['lot_id', 'confirmed_at'], 'rf_lot_confirmed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_fulfillments', function (Blueprint $table) {
            $table->dropIndex('rf_unit_confirmed_idx');
            $table->dropIndex('rf_lot_confirmed_idx');
            $table->dropColumn('confirmed_at');
        });
    }
};
