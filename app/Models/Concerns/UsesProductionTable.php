<?php

namespace App\Models\Concerns;

trait UsesProductionTable
{
    /**
     * Config key under production_schema.tables (e.g. "addrbook").
     */
    protected static function productionTableKey(): string
    {
        throw new \LogicException(static::class.' must implement productionTableKey().');
    }

    public function getTable(): string
    {
        if (! config('production_schema.enabled')) {
            return parent::getTable();
        }

        $key = static::productionTableKey();

        return config("production_schema.tables.{$key}") ?? parent::getTable();
    }
}
