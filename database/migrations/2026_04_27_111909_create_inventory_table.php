<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignId('type_id')->constrained('types');
            $table->foreignId('brand_id')->constrained('brands');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->string('name');
            $table->string('specification')->nullable();
            $table->integer('min_stock_threshold')->default(0)->nullable()->comment('Threshold minimum stock untuk notifikasi barang consumable');
            $table->dateTime('last_restock_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignId('model_id')->constrained('models')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable()->index()->comment('Refers to eproc:vendors.id');
            $table->foreignId('legacy_vendor_id')->nullable()->constrained('vendors')->nullOnDelete()->comment('Refers to local vendors table (legacy)');
            $table->integer('initial_quantity')->nullable();
            $table->integer('current_quantity')->nullable()->comment('for consumables');
            $table->string('entry_type', 50)->default('Procurement');
            $table->string('po_number')->nullable();
            $table->dateTime('purchase_grant_date');
            $table->string('memo_number')->nullable();
            $table->string('memo_url')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete()->comment('for consumables');
            $table->decimal('unit_price', 15, 2)->nullable()->comment('for consumables');
            $table->string('image_url')->nullable()->comment('for consumables');
            $table->string('burden')->nullable()->comment('for consumables');
            $table->unsignedBigInteger('project_id')->nullable()->index()->comment('Refers to RE_PORTALDB:tb_project.id_project (for consumables)');
            $table->timestamps();

            $table->index(['model_id', 'current_quantity', 'purchase_grant_date'], 'lots_model_qty_date_idx');
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('legacy_number', 50)->nullable()->comment('legacy asset code/number');
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->foreignId('legacy_vendor_id')->nullable()->constrained('vendors')->nullOnDelete()->comment('Refers to local vendors table (legacy migration only)');
            $table->foreignId('location_id')->nullable()->constrained('locations')->comment('current location');
            $table->string('status', 50)->default('Belum Diverifikasi');
            $table->string('approval_status', 50)->nullable()->comment('Pending:DM | Pending:BoD/BoC');
            $table->string('condition');
            $table->string('comp_specification')->nullable()->comment('Specification for computer category assets only');
            $table->string('borrowing_type', 2)->default('LT')->comment('LT | ST');
            $table->string('classification', 255)->default('Aset')->comment('Aset | Inventaris');
            $table->decimal('price', 15, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->string('vehicle_registration')->nullable();
            $table->string('burden', 20)->default('Corporate')->nullable()->comment('Corporate | Project');
            $table->unsignedBigInteger('project_id')->nullable()->index()->comment('Refers to RE_PORTALDB:tb_project.id_project');
            $table->boolean('is_assigned_to_location')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index('approval_status');
            $table->index('condition');
            $table->index(['borrowing_type', 'status', 'approval_status'], 'assets_borrow_type_status_approval_idx');
        });

        Schema::create('asset_deletion_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->noActionOnDelete();
            $table->unsignedBigInteger('requester_id')->index()->comment("Refers to new_portal:users.id");
            $table->unsignedBigInteger('approver_id')->nullable()->index()->comment("nullable | Refers to new_portal:users.id");
            $table->string('proposed_condition');
            $table->string('previous_condition');
            $table->string('previous_status')->nullable();
            $table->string('memo_url')->nullable();
            $table->string('lost_doc_url')->nullable();
            $table->string('bod_boc_approval_url')->nullable();
            $table->string('bod_boc_disapproval_url')->nullable();
            $table->string('decision')->default('pending')->comment('pending | approved | rejected');
            $table->text('admin_note')->nullable();
            $table->text('dm_note')->nullable()->comment('nullable | required if rejected');
            $table->dateTime('requested_at');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

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

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->nullable()->constrained('models')->noActionOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->noActionOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->noActionOnDelete();
            $table->unsignedBigInteger('user_id')->index()->comment("Refers to new_portal:users.id");
            $table->string('action_type')->comment('stock_in, stock_out, adjustment, relocation');
            $table->integer('quantity_change')->default(0);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->text('note')->nullable();
            $table->string('document_url')->nullable();
            $table->dateTime('created_at');

            $table->index('created_at');
        });

        Schema::create('asset_lifecycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('action_type')->comment('registrasi, perubahan_status, perubahan_kondisi, pemindahan, peminjaman, pengembalian, pemeliharaan');
            $table->string('status');
            $table->string('condition');
            $table->foreignId('location_id')->nullable()->constrained('locations')->noActionOnDelete();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index()->comment("Siapa yang melakukan aksi | Refers to new_portal:users.id");
            $table->text('note')->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();

            $table->index('start_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_lifecycles');
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('asset_activation_approvals');
        Schema::dropIfExists('asset_deletion_approvals');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('models');
    }
};