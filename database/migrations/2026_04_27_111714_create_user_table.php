<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hrd_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orgchart_id')->nullable(); // FK added after hrd_orgcharts created
            $table->string('employee_id')->unique();
            $table->string('employee_name');
            $table->string('email')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrd_orgcharts', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable();
            $table->string('org_code')->unique();
            $table->string('org_name')->unique();
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('hrd_employees')->nullOnDelete();
        });

        // Now enforce orgchart FK on hrd_employees
        Schema::table('hrd_employees', function (Blueprint $table) {
            $table->foreign('orgchart_id')->references('id')->on('hrd_orgcharts')->nullOnDelete();
        });

        Schema::create('adm_users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('password_hash');
            $table->string('name');
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('hrd_employees')->cascadeOnDelete();
        });

        Schema::create('tb_projects', function (Blueprint $table) {
            $table->id();
            $table->string('no_project')->unique();
            $table->string('project_name');
            $table->string('client_id');
            $table->timestamps();
        });

        Schema::create('tb_rbs', function (Blueprint $table) {
            $table->id('no_urut');
            $table->string('id')->unique();
            $table->string('name');
            $table->string('showing_name');
            $table->timestamps();
        });

        Schema::create('tb_assign_projects', function (Blueprint $table) {
            $table->id();
            $table->string('npk');
            $table->string('no_project');
            $table->string('id_rbs');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->timestamps();

            $table->foreign('npk')->references('employee_id')->on('adm_users')->cascadeOnDelete();
            $table->foreign('no_project')->references('no_project')->on('tb_projects')->cascadeOnDelete();
            $table->foreign('id_rbs')->references('id')->on('tb_rbs')->cascadeOnDelete();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
       });
    }


    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('tb_assign_projects');
        Schema::dropIfExists('tb_rbs');
        Schema::dropIfExists('tb_projects');
        Schema::dropIfExists('adm_users');
        Schema::table('hrd_employees', function (Blueprint $table) {
            $table->dropForeign(['orgchart_id']);
        });
        Schema::dropIfExists('hrd_orgcharts');
        Schema::dropIfExists('hrd_employees');
    }
};