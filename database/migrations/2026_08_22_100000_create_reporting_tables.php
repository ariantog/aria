<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_pkp')->default(false);
            $table->string('npwp', 20)->nullable();
            $table->decimal('modal', 15, 2)->nullable();
            $table->decimal('laba_ditahan_awal', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('reporting_entity_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('bank_id');
        });

        Schema::create('reporting_channel_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('customers')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('customer_id');
        });

        Schema::create('reporting_warehouse_fulfillment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'customer_id']);
        });

        Schema::create('reporting_ledger_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('role', 40);
            $table->timestamps();
        });

        Schema::create('reporting_tax_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_ledger_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
            $table->string('tax_type', 30);
            $table->timestamps();
            $table->unique('legacy_ledger_id');
        });

        Schema::create('ledger_merge_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('new_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique('old_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_merge_maps');
        Schema::dropIfExists('reporting_tax_accounts');
        Schema::dropIfExists('reporting_ledger_roles');
        Schema::dropIfExists('reporting_warehouse_fulfillment');
        Schema::dropIfExists('reporting_channel_banks');
        Schema::dropIfExists('reporting_entity_banks');
        Schema::dropIfExists('reporting_entities');
    }
};
