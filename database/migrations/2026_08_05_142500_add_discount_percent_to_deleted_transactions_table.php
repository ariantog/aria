<?php

use Illuminate\Database\Migrations\Migration;

/** Production `deleted` table uses `discount` for percent — no discount_percent column. */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
