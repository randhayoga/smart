<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE request_returns ALTER COLUMN scheduled_date datetime NULL');
            if (!Schema::hasColumn('request_returns', 'proof_url')) {
                DB::statement('ALTER TABLE request_returns ADD proof_url NVARCHAR(500) NOT NULL');
            }
        } else {
            Schema::table('request_returns', function (Blueprint $table) {
                $table->dateTime('scheduled_date')->nullable()->change();
                if (!Schema::hasColumn('request_returns', 'proof_url')) {
                    $table->string('proof_url')->after('location');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('request_returns', function (Blueprint $table) {
            if (Schema::hasColumn('request_returns', 'proof_url')) {
                $table->dropColumn('proof_url');
            }
            $table->dateTime('scheduled_date')->nullable(false)->change();
        });
    }
};
