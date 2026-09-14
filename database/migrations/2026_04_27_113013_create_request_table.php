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
            $table->unsignedBigInteger('user_id')->index()->comment('Refers to new_portal:users.id');
            $table->unsignedBigInteger('approver_id')->index()->comment('Refers to new_portal:users.id');
            $table->string('utilization')->comment('project | corporate');
            $table->unsignedBigInteger('org_id')->nullable()->index()->comment('Refers to USER_HRIS:hrd_orgchart.id');
            $table->unsignedBigInteger('project_id')->nullable()->index()->comment('Refers to RE_PORTALDB:tb_project.id_project');
            $table->text('reasoning');
            $table->string('status')->comment('overall status e.g., pending | partial | completed');
            $table->timestamps();
        });

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories');
            $table->foreignId('barang_id')->nullable()->constrained('barangs');
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