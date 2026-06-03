<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (config('database.default') === 'sqlsrv') {
            // Drop constraint
            DB::unprepared("
                DECLARE @sql NVARCHAR(MAX) = '';
                SELECT @sql += 'ALTER TABLE [requests] DROP CONSTRAINT [' + name + '];'
                FROM sys.check_constraints
                WHERE parent_object_id = OBJECT_ID('requests')
                  AND parent_column_id = COLUMNPROPERTY(parent_object_id, 'status', 'ColumnId');
                IF @sql <> '' EXEC sp_executesql @sql;
            ");
            
            // Alter column
            DB::statement("ALTER TABLE requests ALTER COLUMN status VARCHAR(50) NOT NULL");
        } else {
            Schema::table('requests', function (Blueprint $table) {
                $table->string('status', 50)->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlsrv') {
            // Alter column back
            DB::statement("ALTER TABLE requests ALTER COLUMN status VARCHAR(255) NOT NULL");
            // Add constraint back
            DB::statement("ALTER TABLE requests ADD CONSTRAINT CK_requests_status CHECK (status IN ('wait', 'approve', 'confirm', 'handover', 'borrow', 'return', 'success', 'reject', 'cancel'))");
        } else {
            Schema::table('requests', function (Blueprint $table) {
                $table->enum('status', ['wait', 'approve', 'confirm', 'handover', 'borrow', 'return', 'success', 'reject', 'cancel'])->change();
            });
        }
    }
};
