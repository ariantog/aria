<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_shop_conversations')) {
            Schema::create('tiktok_shop_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('external_conversation_id', 64)->unique('tts_conv_ext_id_uq');
                $table->string('buyer_nickname', 120);
                $table->string('buyer_avatar_url', 512)->nullable();
                $table->string('order_id', 64)->nullable();
                $table->unsignedSmallInteger('unread_count')->default(0);
                $table->string('last_message_text', 500)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index('last_message_at', 'tts_conv_last_msg_idx');
            });
        }

        if (! Schema::hasTable('tiktok_shop_messages')) {
            Schema::create('tiktok_shop_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tiktok_shop_conversation_id');
                $table->string('external_message_id', 64);
                $table->string('sender_role', 16);
                $table->text('content');
                $table->unsignedInteger('aria_user_id')->nullable();
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->index('tiktok_shop_conversation_id', 'tts_msg_conv_idx');
                $table->unique(['tiktok_shop_conversation_id', 'external_message_id'], 'tts_msg_conv_ext_uq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiktok_shop_messages')) {
            Schema::dropIfExists('tiktok_shop_messages');
        }

        if (Schema::hasTable('tiktok_shop_conversations')) {
            Schema::dropIfExists('tiktok_shop_conversations');
        }
    }
};
