<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barangs', function (Blueprint $table) {
            $table->id();
            $table->char('number', 12)->unique();
            $table->foreignId('subcategory_id')->constrained('subcategories');
            $table->foreignId('brand_id')->constrained('brands');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->enum('type', ['consumable', 'asset']);
            $table->string('specification');
            $table->string('image_url');
            $table->timestamps();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->char('number', 26)->unique();
            $table->foreignId('barang_id')->constrained('barangs');
            $table->foreignId('organizer_id')->constrained('organizers');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('po_number');
            $table->dateTime('date_of_receipt');
            $table->decimal('unit_price', 15, 2);
            $table->string('image_url');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->char('number', 25)->unique();
            $table->foreignId('lot_id')->constrained('lots');
            $table->foreignId('location_id')->constrained('locations');
            $table->enum('status', ['tersedia', 'dipinjam', 'dipakai', 'nonaktif']);
            $table->string('condition');
            $table->decimal('price', 15, 2);
            $table->string('image_url');
            $table->string('vehicle_registration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('barangs');
    }
};
