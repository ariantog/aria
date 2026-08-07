<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow user deletion while preserving historical records that reference them.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('borongans', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('borongans', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        if (Schema::hasTable('stok_reports')) {
            Schema::table('stok_reports', function (Blueprint $table) {
                $table->dropForeign(['generet_by']);
            });

            Schema::table('stok_reports', function (Blueprint $table) {
                $table->foreign('generet_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stok_reports')) {
            Schema::table('stok_reports', function (Blueprint $table) {
                $table->dropForeign(['generet_by']);
            });

            Schema::table('stok_reports', function (Blueprint $table) {
                $table->foreign('generet_by')
                    ->references('id')
                    ->on('users');
            });
        }

        Schema::table('borongans', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('borongans', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }
};
