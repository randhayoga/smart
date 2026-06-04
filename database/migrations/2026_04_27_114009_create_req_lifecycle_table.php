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
            $table->foreignId('approver_id')->constrained('adm_users');
            $table->string('decision')->comment('approved | rejected');
            $table->text('note')->nullable()->comment('nullable | required if rejected');
            $table->dateTime('decided_at');
            $table->timestamps();
        });

        Schema::create('request_admin_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('adm_users');
            $table->string('action')->comment('confirm | reject | pending');
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
            $table->boolean('is_auto_set');
            $table->dateTime('user_confirmed_at')->nullable();
            $table->boolean('auto_confirmed')->default(false);
            $table->dateTime('admin_cancelled_at')->nullable();
            $table->text('note')->nullable()->comment('nullable | required if cancelled');
            $table->timestamps();
        });

        Schema::create('request_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('method')->comment('pickup (active) | delivery (inactive)');
            $table->dateTime('scheduled_date');
            $table->string('location')->comment("default to Ruang IFS & can't be changed");
            $table->boolean('is_auto_set');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('status_from');
            $table->string('status_to');
            $table->foreignId('changed_by')->nullable()->constrained('adm_users');
            $table->text('note')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('request_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_item_id')->constrained('request_items')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->comment('nullable | for assets');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->comment('nullable | for consumables');
            $table->foreignId('handover_id')->nullable()->constrained('request_handovers')->comment('nullable | links to schedule');
            $table->foreignId('return_id')->nullable()->constrained('request_returns')->comment('nullable | links to return schedule');
            $table->integer('quantity_fulfilled')->default(1)->comment('defaults to 1 for assets');
            $table->dateTime('assigned_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_fulfillments');
        Schema::dropIfExists('request_status_logs');
        Schema::dropIfExists('request_returns');
        Schema::dropIfExists('request_handovers');
        Schema::dropIfExists('request_admin_confirmations');
        Schema::dropIfExists('request_approvals');
    }
};