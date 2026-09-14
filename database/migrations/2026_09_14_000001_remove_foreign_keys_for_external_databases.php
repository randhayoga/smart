<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Drops foreign keys referencing external databases and removes dummy local tables.
     */
    public function up(): void
    {
        $fks = [
            ['requests', 'requests_user_id_foreign'],
            ['requests', 'requests_approver_id_foreign'],
            ['requests', 'requests_org_id_foreign'],
            ['requests', 'requests_project_id_foreign'],
            ['consumable_baskets', 'consumable_baskets_user_id_foreign'],
            ['asset_baskets', 'asset_baskets_user_id_foreign'],
            ['unit_status_approvals', 'unit_status_approvals_requester_id_foreign'],
            ['unit_status_approvals', 'unit_status_approvals_approver_id_foreign'],
            ['inventory_logs', 'inventory_logs_user_id_foreign'],
            ['unit_lifecycles', 'unit_lifecycles_actor_id_foreign'],
            ['lots', 'lots_project_id_foreign'],
            ['request_approvals', 'request_approvals_approver_id_foreign'],
            ['request_admin_confirmations', 'request_admin_confirmations_admin_id_foreign'],
            ['request_status_logs', 'request_status_logs_changed_by_foreign'],
            ['tb_assign_projects', 'tb_assign_projects_npk_foreign'],
            ['tb_assign_projects', 'tb_assign_projects_no_project_foreign'],
            ['tb_assign_projects', 'tb_assign_projects_id_rbs_foreign'],
            ['hrd_orgcharts', 'hrd_orgcharts_employee_id_foreign'],
            ['adm_users', 'adm_users_employee_id_foreign'],
            ['hrd_employees', 'hrd_employees_orgchart_id_foreign'],
        ];

        foreach ($fks as [$table, $fk]) {
            DB::statement("
                IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = '{$fk}')
                BEGIN
                    ALTER TABLE [{$table}] DROP CONSTRAINT [{$fk}]
                END
            ");
        }

        Schema::dropIfExists('tb_assign_projects');
        Schema::dropIfExists('tb_rbs');
        Schema::dropIfExists('tb_projects');
        Schema::dropIfExists('adm_users');
        Schema::dropIfExists('hrd_employees');
        Schema::dropIfExists('hrd_orgcharts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse migration is not applicable as these tables now reside in external databases
    }
};
