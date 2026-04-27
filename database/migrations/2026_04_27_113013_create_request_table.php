<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->char('request_number', 11)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('approver_id')->constrained('users');
            $table->enum('utilization', ['project', 'corporate']);
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->text('reasoning');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->enum('status', ['wait', 'approve', 'confirm', 'handover', 'borrow', 'return', 'success', 'reject', 'cancel']);
            $table->timestamps();
        });

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barangs');
            $table->integer('quantity_requested');
            $table->timestamps();
        });

        Schema::create('request_unit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_item_id')->constrained('request_items')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->dateTime('assigned_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_unit_assignments');
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
    }
};