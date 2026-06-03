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
                SELECT @sql += 'ALTER TABLE [request_admin_confirmations] DROP CONSTRAINT [' + name + '];'
                FROM sys.check_constraints
                WHERE parent_object_id = OBJECT_ID('request_admin_confirmations')
                  AND parent_column_id = COLUMNPROPERTY(parent_object_id, 'action', 'ColumnId');
                IF @sql <> '' EXEC sp_executesql @sql;
            ");
            
            // Alter column
            DB::statement("ALTER TABLE request_admin_confirmations ALTER COLUMN action VARCHAR(50) NOT NULL");
        } else {
            Schema::table('request_admin_confirmations', function (Blueprint $table) {
                $table->string('action', 50)->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlsrv') {
            // Alter column back
            DB::statement("ALTER TABLE request_admin_confirmations ALTER COLUMN action VARCHAR(255) NOT NULL");
            // Add constraint back
            DB::statement("ALTER TABLE request_admin_confirmations ADD CONSTRAINT CK_request_admin_confirmations_action CHECK (action IN ('confirm', 'reject'))");
        } else {
            Schema::table('request_admin_confirmations', function (Blueprint $table) {
                $table->enum('action', ['confirm', 'reject'])->change();
            });
        }
    }
};
