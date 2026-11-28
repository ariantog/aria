<?php

namespace App\Services\Marketplace;

use App\Models\OmnichannelSetting;
use Illuminate\Support\Facades\Schema;

class OmnichannelSettingStore
{
    public const KEY_SHOPEE_LOW_STOCK_THRESHOLD = 'shopee_low_stock_threshold';

    public const KEY_TOKOPEDIA_STORE_ID = 'tokopedia_store_id';

    public const KEY_TOKOPEDIA_NOTES = 'tokopedia_notes';

    public const KEY_FLEXI_STAGING_WAREHOUSE_ID = 'flexi_staging_warehouse_id';

    public const KEY_POST_SELL_GATE = 'post_sell_gate';

    public const POST_SELL_GATE_SHIPPED = 'shipped';

    public const POST_SELL_GATE_AWB_PRINTED = 'awb_printed';

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

    /**
     * Runtime post-sell gate: env/config default, overridden when omnichannel_settings has a valid value.
     */
    public function resolvePostSellGate(): string
    {
        $configDefault = strtolower(trim((string) config('marketplace.post_sell.gate', self::POST_SELL_GATE_SHIPPED)));
        if (! in_array($configDefault, [self::POST_SELL_GATE_SHIPPED, self::POST_SELL_GATE_AWB_PRINTED], true)) {
            $configDefault = self::POST_SELL_GATE_SHIPPED;
        }

        $stored = strtolower(trim((string) $this->getString(self::KEY_POST_SELL_GATE, '')));
        if (in_array($stored, [self::POST_SELL_GATE_SHIPPED, self::POST_SELL_GATE_AWB_PRINTED], true)) {
            return $stored;
        }

        return $configDefault;
    }
}