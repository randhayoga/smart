<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_activation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedBigInteger('requester_id')->index()->comment('Refers to new_portal:users.id');
            $table->unsignedBigInteger('approver_id')->nullable()->index()->comment('nullable | Refers to new_portal:users.id');
            $table->string('decision')->default('pending')->comment('pending | approved | rejected');
            $table->text('note')->nullable()->comment('nullable | decision explanation note');
            $table->dateTime('requested_at');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_activation_approvals');
    }
};
