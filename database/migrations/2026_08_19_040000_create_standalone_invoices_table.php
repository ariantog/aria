<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standalone_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->date('date');
            $table->string('recipient_name');
            $table->foreignId('recipient_addrbook_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sender_addrbook_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('template', 32)->default('classic');
            $table->text('terms_of_payment')->nullable();
            $table->text('pay_to')->nullable();
            $table->string('signatory_name')->nullable();
            $table->decimal('total_qty', 16, 4)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index('number');
        });

        Schema::create('standalone_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standalone_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->string('description');
            $table->decimal('quantity', 16, 4)->default(0);
            $table->decimal('price', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standalone_invoice_lines');
        Schema::dropIfExists('standalone_invoices');
    }
};
