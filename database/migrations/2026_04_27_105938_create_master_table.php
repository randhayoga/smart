<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 9)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_consumable')->default(true);
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('satuan barang');
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->comment('Legacy vendors table managed in Master Data for existing inventory/assets');
            $table->id();
            $table->string('code', 7)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors_cp', function (Blueprint $table) {
            $table->comment('Vendor contact persons (1:N relationship)');
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('contact_person')->nullable();
            $table->string('cp_email')->nullable();
            $table->string('cp_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('locations')->noActionOnDelete();
            $table->unsignedBigInteger('related_departement')->nullable()->index()->comment('Refers to USER_HRIS:hrd_orgchart.id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('vendors_cp');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('organizers');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('types');
        Schema::dropIfExists('categories');
    }
};