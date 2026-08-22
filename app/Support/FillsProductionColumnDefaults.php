<?php

namespace App\Support;

trait FillsProductionColumnDefaults
{
    protected static function bootFillsProductionColumnDefaults(): void
    {
        static::creating(function ($model): void {
            ProductionColumnDefaults::apply($model);
        });

        static::updating(function ($model): void {
            ProductionColumnDefaults::apply($model);
        });
    }
}
