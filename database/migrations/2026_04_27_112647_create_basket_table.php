<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consumable_baskets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('Refers to new_portal:users.id');
            $table->foreignId('type_id')->nullable()->constrained('types')->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('models')->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();
        });

        Schema::create('asset_baskets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('Refers to new_portal:users.id');
            $table->foreignId('type_id')->nullable()->constrained('types')->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('models')->cascadeOnDelete();
            $table->integer('quantity');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_baskets');
        Schema::dropIfExists('consumable_baskets');
    }
};