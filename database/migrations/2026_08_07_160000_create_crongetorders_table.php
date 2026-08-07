<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crongetorders', function (Blueprint $table) {
            $table->id();
            $table->date('from');
            $table->unsignedInteger('to')->comment('Number of days after from date');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('count')->default(0);
            $table->unsignedTinyInteger('step')->default(1);
            $table->unsignedTinyInteger('status')->default(0)->comment('0=running, 1=done');
            $table->boolean('cek_transaction')->default(false);
            $table->timestamps();
        });

        Schema::create('crongetorderdetails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crongetorder_id')->constrained('crongetorders')->cascadeOnDelete();
            $table->string('jubelio_order_id')->nullable();
            $table->string('invoice')->nullable()->index();
            $table->string('location_name')->nullable();
            $table->string('store_name')->nullable();
            $table->string('order_status')->nullable();
            $table->char('is_canceled', 1)->nullable();
            $table->longText('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crongetorderdetails');
        Schema::dropIfExists('crongetorders');
    }
};
