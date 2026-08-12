<?php

namespace App\Support;

class ProductionSchema
{
    public static function enabled(): bool
    {
        return (bool) config('production_schema.enabled');
    }

    public static function table(string $key): string
    {
        if (! self::enabled()) {
            return match ($key) {
                'addrbook' => 'addrbooks',
                'addrbook_stat' => 'addrbook_stats',
                'addrbook_daily' => 'addrbook_dailies',
                'item_group' => 'item_groups',
                'warehouse_item' => 'warehouse_items',
                'produksi' => 'produksis',
                'borongan' => 'borongans',
                'borongan_detail' => 'borongan_details',
                'worker' => 'workers',
                'deleted_transaction' => 'deleted_transactions',
                'deleted_transaction_detail' => 'deleted_transaction_details',
                default => throw new \InvalidArgumentException("Unknown schema key [{$key}]"),
            };
        }

        return config("production_schema.tables.{$key}")
            ?? throw new \InvalidArgumentException("Unknown schema key [{$key}]");
    }

    public static function column(string $tableKey, string $l12Column): string
    {
        if (! self::enabled()) {
            return $l12Column;
        }

        return config("production_schema.columns.{$tableKey}.{$l12Column}") ?? $l12Column;
    }
}
