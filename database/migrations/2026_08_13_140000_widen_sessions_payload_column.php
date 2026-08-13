<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'payload')) {
      return;
    }

    ProductionMysqlCompat::alterTable('sessions', function () {
      Schema::table('sessions', function (Blueprint $table) {
        $table->longText('payload')->change();
      });
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'payload')) {
      return;
    }

    ProductionMysqlCompat::alterTable('sessions', function () {
      Schema::table('sessions', function (Blueprint $table) {
        $table->text('payload')->change();
      });
    });
  }
};
