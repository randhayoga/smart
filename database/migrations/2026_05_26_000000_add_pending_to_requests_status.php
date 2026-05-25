<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('wait', 'approve', 'confirm', 'handover', 'borrow', 'return', 'success', 'reject', 'cancel', 'pending') NOT NULL DEFAULT 'wait'");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('wait', 'approve', 'confirm', 'handover', 'borrow', 'return', 'success', 'reject', 'cancel') NOT NULL DEFAULT 'wait'");
        }
    }
};
