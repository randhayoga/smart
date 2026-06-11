<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 11)->unique();
            $table->foreignId('user_id')->constrained('adm_users');
            $table->foreignId('approver_id')->constrained('adm_users');
            $table->string('utilization')->comment('project | corporate');
            $table->foreignId('org_id')->nullable()->constrained('hrd_orgcharts');
            $table->foreignId('project_id')->nullable()->constrained('tb_projects');
            $table->text('reasoning');
            $table->string('status')->comment('overall status e.g., pending | partial | completed');
            $table->timestamps();
        });

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained('subcategories');
            $table->foreignId('barang_id')->constrained('barangs');
            $table->integer('quantity_requested');
            $table->dateTime('start_date')->nullable()->comment('nullable; assets only');
            $table->dateTime('end_date')->nullable()->comment('nullable; assets only');
            $table->string('status')->default('pending')->comment('pending | partially_fulfilled | fulfilled | cancelled');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
    }
};