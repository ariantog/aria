<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jubelio_proxy_clients')) {
            Schema::create('jubelio_proxy_clients', function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->string('token_prefix', 12);
                $table->string('token_hash', 64)->unique();
                $table->json('allowed_apis');
                $table->json('scopes')->nullable();
                $table->boolean('write_enabled')->default(false);
                $table->unsignedSmallInteger('rate_limit_per_minute')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('jubelio_proxy_request_logs')) {
            Schema::create('jubelio_proxy_request_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('client_name', 64)->nullable();
                $table->string('api_name', 64);
                $table->json('params_redacted')->nullable();
                $table->unsignedSmallInteger('jubelio_status')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->string('error', 255)->nullable();
                $table->timestamp('requested_at');
                $table->index(['client_id', 'requested_at'], 'jbp_log_client_time_idx');
                $table->index(['api_name', 'requested_at'], 'jbp_log_api_time_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jubelio_proxy_request_logs');
        Schema::dropIfExists('jubelio_proxy_clients');
    }
};