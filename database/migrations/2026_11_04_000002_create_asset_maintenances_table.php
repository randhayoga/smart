<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedBigInteger('maintainer_id')->index()->comment('Refers to new_portal:users.id (maintainer/admin)');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('Refers to new_portal:users.id (requester/assigned user)');
            $table->string('type', 50)->comment('Preventive | Corrective');
            $table->string('source', 50)->comment('SMART | TiSu');
            $table->string('reference')->nullable();
            $table->text('problem');
            $table->text('solution');
            $table->decimal('cost', 15, 2)->default(0);
            $table->date('date');
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'date'], 'asset_maintenances_asset_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
