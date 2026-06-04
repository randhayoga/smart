<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barangs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 12)->unique();
            $table->foreignId('subcategory_id')->constrained('subcategories');
            $table->foreignId('brand_id')->constrained('brands');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->string('specification');
            $table->string('image_url')->comment('default image');
            $table->dateTime('last_restock_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('number', 26)->unique();
            $table->foreignId('barang_id')->constrained('barangs')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->comment('default location');
            $table->foreignId('floor_id')->nullable()->constrained('floors')->comment('nullable | default location');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->comment('nullable | default location');
            $table->integer('initial_quantity');
            $table->integer('current_quantity')->nullable()->comment('for consumables');
            $table->string('po_number');
            $table->dateTime('date_of_receipt');
            $table->decimal('unit_price', 15, 2)->comment('default unit price');
            $table->string('image_url')->comment('default image');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->comment('current location');
            $table->foreignId('floor_id')->nullable()->constrained('floors');
            $table->foreignId('room_id')->nullable()->constrained('rooms');
            $table->string('status');
            $table->string('condition');
            $table->decimal('price', 15, 2);
            $table->string('image_url');
            $table->string('vehicle_registration')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('adm_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('unit_status_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->noActionOnDelete();
            $table->foreignId('requester_id')->constrained('adm_users')->comment("ADM_USER's id")->noActionOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('adm_users')->comment("nullable | ADM_USER's id")->noActionOnDelete();
            $table->string('proposed_status');
            $table->string('decision')->default('pending')->comment('pending | approved | rejected');
            $table->text('note')->nullable()->comment('nullable | required if rejected');
            $table->dateTime('requested_at');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->nullable()->constrained('barangs')->noActionOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->noActionOnDelete();
            $table->foreignId('user_id')->constrained('adm_users')->noActionOnDelete();
            $table->string('action_type')->comment('stock_in, stock_out, adjustment, relocation');
            $table->integer('quantity_change')->default(0);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->text('note')->nullable();
            $table->string('document_url')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('unit_lifecycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->noActionOnDelete();
            $table->string('status')->comment('available | repair | lost | broken | inactive');
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->foreignId('requester_id')->constrained('adm_users')->noActionOnDelete()->comment("ADM_USER's id | who created/requested the state");
            $table->foreignId('approver_id')->nullable()->constrained('adm_users')->noActionOnDelete()->comment("nullable | ADM_USER's id | who approved it");
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_lifecycles');
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('unit_status_approvals');
        Schema::dropIfExists('units');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('barangs');
    }
};