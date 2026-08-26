<?php

namespace App\Enums;

enum ShopeeAdsAutomationStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public static function fromStored(?string $value): self
    {
        return strtolower(trim((string) $value)) === self::Paused->value
            ? self::Paused
            : self::Active;
    }
}
