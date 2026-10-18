<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_alerts')) {
            Schema::create('operational_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 64);
                $table->string('fingerprint', 191);
                $table->string('severity', 16)->default('info');
                $table->json('payload')->nullable();
                $table->string('status', 16)->default('open');
                $table->timestamp('first_seen_at')->useCurrent();
                $table->timestamp('last_seen_at')->useCurrent();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('rearmed_at')->nullable();
                $table->timestamps();

                $table->unique('fingerprint', 'ops_alert_fp_uq');
                $table->index(['event_key', 'status'], 'ops_alert_evt_st_idx');
            });
        }

        if (! Schema::hasTable('notification_log')) {
            Schema::create('notification_log', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 32)->default('lark');
                $table->string('event_key', 64)->nullable();
                $table->string('fingerprint', 191)->nullable();
                $table->string('chat_id', 128)->nullable();
                $table->string('message_id', 128)->nullable();
                $table->string('payload_hash', 64)->nullable();
                $table->string('status', 16)->default('pending');
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['fingerprint', 'status'], 'notif_log_fp_st_idx');
            });
        }

        if (! Schema::hasTable('lark_restock_requests')) {
            Schema::create('lark_restock_requests', function (Blueprint $table) {
                $table->id();
                $table->string('token', 36)->unique();
                $table->unsignedInteger('item_id');
                $table->unsignedInteger('suggested_qty')->default(0);
                $table->unsignedInteger('applied_qty')->nullable();
                $table->unsignedInteger('submitted_by')->nullable();
                $table->json('context_snapshot')->nullable();
                $table->string('status', 24)->default('pending');
                $table->text('result_message')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['item_id', 'status'], 'lark_restock_item_st_idx');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'lark_open_id')) {
                    $table->string('lark_open_id', 64)->nullable()->after('username');
                }
                if (! Schema::hasColumn('users', 'lark_notify_enabled')) {
                    $table->boolean('lark_notify_enabled')->default(true)->after('lark_open_id');
                }
            });
        }

        if (Schema::hasTable('jubelio_stock_discrepancies')) {
            Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
                if (! Schema::hasColumn('jubelio_stock_discrepancies', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable();
                }
                if (! Schema::hasColumn('jubelio_stock_discrepancies', 'resolved_by')) {
                    $table->unsignedInteger('resolved_by')->nullable();
                }
                if (! Schema::hasColumn('jubelio_stock_discrepancies', 'resolve_note')) {
                    $table->string('resolve_note', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lark_restock_requests');
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('operational_alerts');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['lark_notify_enabled', 'lark_open_id'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('jubelio_stock_discrepancies')) {
            Schema::table('jubelio_stock_discrepancies', function (Blueprint $table) {
                foreach (['resolve_note', 'resolved_by', 'resolved_at'] as $column) {
                    if (Schema::hasColumn('jubelio_stock_discrepancies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
