<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared CREATE for omnichannel_settings (key/value store for omnichannel UI).
 */
function aria_create_omnichannel_settings_table(): void
{
    Schema::create('omnichannel_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key', 64);
        $table->text('value')->nullable();
        $table->timestamps();

        $table->unique('key', 'omni_settings_key_uidx');
    });
}