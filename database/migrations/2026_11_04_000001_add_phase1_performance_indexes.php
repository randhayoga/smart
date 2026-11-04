<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->index(['lot_id', 'status'], 'assets_lot_status_idx');
            $table->index(['status', 'created_at'], 'assets_status_created_idx');
            $table->index('location_id', 'assets_location_id_idx');
        });

        Schema::table('asset_deletion_approvals', function (Blueprint $table) {
            $table->index(['asset_id', 'decision', 'id'], 'asset_del_app_asset_dec_id_idx');
            $table->index(['decision', 'id'], 'asset_del_app_decision_id_idx');
        });

        Schema::table('asset_activation_approvals', function (Blueprint $table) {
            $table->index(['asset_id', 'decision', 'id'], 'asset_act_app_asset_dec_id_idx');
            $table->index(['decision', 'id'], 'asset_act_app_decision_id_idx');
        });

        Schema::table('asset_lifecycles', function (Blueprint $table) {
            $table->index(['asset_id', 'id'], 'asset_lifecycles_asset_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asset_lifecycles', function (Blueprint $table) {
            $table->dropIndex('asset_lifecycles_asset_id_idx');
        });

        Schema::table('asset_activation_approvals', function (Blueprint $table) {
            $table->dropIndex('asset_act_app_asset_dec_id_idx');
            $table->dropIndex('asset_act_app_decision_id_idx');
        });

        Schema::table('asset_deletion_approvals', function (Blueprint $table) {
            $table->dropIndex('asset_del_app_asset_dec_id_idx');
            $table->dropIndex('asset_del_app_decision_id_idx');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('assets_lot_status_idx');
            $table->dropIndex('assets_status_created_idx');
            $table->dropIndex('assets_location_id_idx');
        });
    }
};
