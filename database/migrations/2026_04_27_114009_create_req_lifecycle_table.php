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
            $table->foreignId('approver_id')->constrained('users');
            $table->enum('decision', ['approve', 'reject']);
            $table->text('note')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();
        });

        Schema::create('request_admin_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users');
            $table->enum('action', ['confirm', 'reject']);
            $table->text('note')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();
        });

        Schema::create('request_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->enum('method', ['delivery', 'pickup']);
            $table->dateTime('scheduled_date');
            $table->string('location');
            $table->boolean('is_auto_set');
            $table->dateTime('user_confirmed_at')->nullable();
            $table->boolean('auto_confirmed')->default(false);
            $table->dateTime('admin_cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('request_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->enum('method', ['self', 'delivery']);
            $table->dateTime('scheduled_date');
            $table->string('location');
            $table->boolean('is_auto_set');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('status_from');
            $table->string('status_to');
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent(); // Single timestamp per ERD
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_status_logs');
        Schema::dropIfExists('request_returns');
        Schema::dropIfExists('request_handovers');
        Schema::dropIfExists('request_admin_confirmations');
        Schema::dropIfExists('request_approvals');
    }
};