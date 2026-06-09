<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_status_approvals', function (Blueprint $table) {
            $table->string('memo_path')->nullable()->after('proposed_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_status_approvals', function (Blueprint $table) {
            $table->dropColumn(['memo_path']);
        });
    }
};
