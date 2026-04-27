<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Nullable initially to prevent circular dependency issues during creation
            $table->unsignedBigInteger('manager_id')->nullable(); 
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['superadmin', 'admin', 'user', 'manager']);
            $table->foreignId('department_id')->constrained('departments');
            $table->timestamps();
        });

        // Now that users exist, enforce the manager foreign key on departments
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('users');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->foreignId('manager_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('project_user', function (Blueprint $table) { // Standard Laravel pivot naming
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('users');
        Schema::dropIfExists('departments');
    }
};