<?php

namespace App\Services\Marketplace;

use App\Models\OmnichannelSetting;
use Illuminate\Support\Facades\Schema;

class OmnichannelSettingStore
{
    public const KEY_SHOPEE_LOW_STOCK_THRESHOLD = 'shopee_low_stock_threshold';

    public function tableExists(): bool
    {
        return Schema::hasTable('omnichannel_settings');
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        if (! $this->tableExists()) {
            return $default;
        }

        $row = OmnichannelSetting::query()->where('key', $key)->first();

        return $row !== null ? (string) $row->value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $raw = $this->getString($key, (string) $default);

        return is_numeric($raw) ? (int) $raw : $default;
    }

    public function set(string $key, string $value): void
    {
        if (! $this->tableExists()) {
            return;
        }

        OmnichannelSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}