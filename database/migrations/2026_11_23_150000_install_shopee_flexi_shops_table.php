<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopee_flexi_shops')) {
            Schema::create('shopee_flexi_shops', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('shop_id');
                $table->string('invoice_prefix', 10)->default('SP');
                $table->boolean('direct_order_ingest')->default(true);
                $table->string('label', 255)->default('');
                $table->timestamp('last_order_poll_at')->nullable();
                $table->timestamps();

                $table->unique('shop_id', 'shopee_flexi_shop_uq');
            });
        }

        if (Schema::hasTable('shopee_syncs') && Schema::hasTable('shopee_flexi_shops')) {
            $rows = DB::table('shopee_syncs')
                ->where('shop_id', '>', 0)
                ->orderBy('id')
                ->get(['shop_id', 'invoice_prefix', 'direct_order_ingest', 'last_order_poll_at']);

            $seen = [];
            foreach ($rows as $row) {
                $shopId = (int) $row->shop_id;
                if ($shopId <= 0 || isset($seen[$shopId])) {
                    continue;
                }

                $prefix = strtoupper((string) ($row->invoice_prefix ?? 'SP'));
                $ingest = (bool) ($row->direct_order_ingest ?? false);
                if (! $ingest && ($prefix === 'SP' || $prefix === '')) {
                    continue;
                }

                $seen[$shopId] = true;

                $exists = DB::table('shopee_flexi_shops')->where('shop_id', $shopId)->exists();
                if ($exists) {
                    continue;
                }

                DB::table('shopee_flexi_shops')->insert([
                    'shop_id' => $shopId,
                    'invoice_prefix' => $prefix !== '' ? $prefix : 'SP',
                    'direct_order_ingest' => $ingest,
                    'label' => '',
                    'last_order_poll_at' => $row->last_order_poll_at ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shopee_flexi_shops')) {
            Schema::dropIfExists('shopee_flexi_shops');
        }
    }
};