<?php

namespace App\Enums;

enum AddrbookType: int
{
    case Customer = 1;
    case Warehouse = 2;
    case Bank = 3;
    case Supplier = 4;
    case VirtualWarehouse = 5;
    case VirtualAccount = 6;
    case Reseller = 7;
    case Account = 8;
    case Other = 99;

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Warehouse => 'Warehouse',
            self::Bank => 'Bank (Account)',
            self::Supplier => 'Supplier',
            self::VirtualWarehouse => 'V.Warehouse',
            self::VirtualAccount => 'V.Account',
            self::Reseller => 'Reseller',
            self::Account => 'Account',
            self::Other => 'Other',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::Customer => 'customer',
            self::Warehouse => 'warehouse',
            self::Bank => 'bank',
            self::Supplier => 'supplier',
            self::VirtualWarehouse => 'vwarehouse',
            self::VirtualAccount => 'vaccount',
            self::Reseller => 'reseller',
            self::Account => 'account',
            self::Other => 'other',
        };
    }

    public function allowsNegativeStock(): bool
    {
        return $this === self::VirtualWarehouse;
    }

    public function isWarehouse(): bool
    {
        return in_array($this, [self::Warehouse, self::VirtualWarehouse], true);
    }

    public function isFinancial(): bool
    {
        return in_array($this, [self::Bank, self::Account, self::VirtualAccount], true);
    }

    public function supportsItemSales(): bool
    {
        return ! $this->isFinancial();
    }

    public function hasWarehouseStock(): bool
    {
        return $this->isWarehouse();
    }
}
