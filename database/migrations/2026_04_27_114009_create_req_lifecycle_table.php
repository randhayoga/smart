<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->unsignedBigInteger('approver_id')->index()->comment('Refers to new_portal:users.id');
            $table->string('decision')->comment('approved | rejected');
            $table->text('note')->nullable()->comment('nullable | required if rejected');
            $table->dateTime('decided_at');
            $table->timestamps();
        });

        Schema::create('request_admin_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->index()->comment('Refers to new_portal:users.id');
            $table->string('action')->comment('confirm | reject');
            $table->text('note')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();
        });

        Schema::create('request_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('method')->comment('delivery | pickup');
            $table->dateTime('scheduled_date');
            $table->string('location');
            $table->string('proof_url')->nullable();
            $table->dateTime('user_confirmed_at')->nullable();
            $table->boolean('auto_confirmed')->default(false);
            $table->dateTime('admin_cancelled_at')->nullable();
            $table->text('note')->nullable()->comment('nullable | required if cancelled');
            $table->timestamps();
        });

        Schema::create('request_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('method')->comment('pickup | delivery | manual');
            $table->dateTime('scheduled_date')->nullable();
            $table->string('location');
            $table->string('proof_url');
            $table->boolean('is_auto_set');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('status_from');
            $table->string('status_to');
            $table->unsignedBigInteger('changed_by')->nullable()->index()->comment('Refers to new_portal:users.id');
            $table->text('note')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('request_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_item_id')->constrained('request_items')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->comment('nullable | for assets');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->comment('nullable | for consumables');
            $table->foreignId('handover_id')->nullable()->constrained('request_handovers')->comment('nullable | links to schedule');
            $table->foreignId('return_id')->nullable()->constrained('request_returns')->comment('nullable | links to return schedule');
            $table->integer('quantity_fulfilled')->default(1)->comment('defaults to 1 for assets');
            $table->dateTime('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'assigned_at', 'handover_id', 'return_id'], 'rf_asset_status_idx');
            $table->index(['lot_id', 'assigned_at', 'handover_id'], 'rf_lot_status_idx');
        });

        Schema::table('asset_lifecycles', function (Blueprint $table) {
            $table->unsignedBigInteger('request_id')->nullable()->index();
            $table->unsignedBigInteger('fulfillment_id')->nullable()->index();

            $table->foreign('request_id', 'fk_asset_lifecycles_request_id')
                ->references('id')
                ->on('requests')
                ->noActionOnDelete();

            $table->foreign('fulfillment_id', 'fk_asset_lifecycles_fulfillment_id')
                ->references('id')
                ->on('request_fulfillments')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_lifecycles', function (Blueprint $table) {
            $table->dropForeign('fk_asset_lifecycles_request_id');
            $table->dropForeign('fk_asset_lifecycles_fulfillment_id');
            $table->dropColumn(['request_id', 'fulfillment_id']);
        });

        Schema::dropIfExists('request_fulfillments');
        Schema::dropIfExists('request_status_logs');
        Schema::dropIfExists('request_returns');
        Schema::dropIfExists('request_handovers');
        Schema::dropIfExists('request_admin_confirmations');
        Schema::dropIfExists('request_approvals');
    }
};