<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->boolean('is_consumable')->default(true);
            $table->timestamps();
        });

        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 7)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('satuan barang');
            $table->timestamps();
        });

        // Grouping simple reference tables
        $references = ['brands', 'organizers', 'vendors', 'locations'];
        foreach ($references as $ref) {
            Schema::create($ref, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('floor_id')->constrained('floors')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('organizers');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('subcategories');
        Schema::dropIfExists('categories');
    }
};