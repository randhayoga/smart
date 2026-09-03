<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill existing rows with UUIDv7
        $requests = DB::table('requests')->whereNull('uuid')->get(['id']);
        foreach ($requests as $req) {
            DB::table('requests')
                ->where('id', $req->id)
                ->update(['uuid' => (string) Str::uuid7()]);
        }

        Schema::table('requests', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
