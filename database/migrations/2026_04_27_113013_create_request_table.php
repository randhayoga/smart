<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('request_number', 11)->unique();
            $table->unsignedBigInteger('user_id')->index()->comment('Refers to new_portal:users.id');
            $table->unsignedBigInteger('approver_id')->index()->comment('Refers to new_portal:users.id');
            $table->string('utilization')->nullable()->comment('project | corporate');
            $table->unsignedBigInteger('org_id')->nullable()->index()->comment('Refers to USER_HRIS:hrd_orgchart.id');
            $table->unsignedBigInteger('project_id')->nullable()->index()->comment('Refers to RE_PORTALDB:tb_project.id_project');
            $table->text('reasoning')->nullable();
            $table->string('form_url')->nullable();
            $table->string('status')->comment('overall status e.g., pending | partial | completed');
            $table->timestamps();
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlsrv') {
            DB::statement('CREATE UNIQUE NONCLUSTERED INDEX requests_uuid_unique ON requests(uuid) WHERE uuid IS NOT NULL');
        } else {
            Schema::table('requests', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('types');
            $table->foreignId('model_id')->nullable()->constrained('models');
            $table->integer('quantity_requested');
            $table->dateTime('start_date')->nullable()->comment('nullable; assets only');
            $table->dateTime('end_date')->nullable()->comment('nullable; assets only');
            $table->text('reasoning')->nullable();
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