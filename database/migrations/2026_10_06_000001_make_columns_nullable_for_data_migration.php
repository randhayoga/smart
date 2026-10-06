<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('utilization')->nullable()->change();
        });

        // Ensure requests_uuid_unique allows multiple NULL values during migration on SQL Server
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlsrv') {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS requests_uuid_unique ON requests');
            \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE NONCLUSTERED INDEX requests_uuid_unique ON requests(uuid) WHERE uuid IS NOT NULL');
        }

        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->change();
            $table->integer('initial_quantity')->nullable()->change();
            $table->integer('current_quantity')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('utilization')->nullable(false)->change();
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
            $table->integer('initial_quantity')->nullable(false)->change();
            $table->integer('current_quantity')->nullable()->change();
        });
    }
};
