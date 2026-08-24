<?php

namespace App\Enums;

enum ReportingLedgerRole: string
{
    case Material = 'material';
    case ProductionCost = 'production_cost';
    case MarketplaceCost = 'marketplace_cost';
    case TokoCost = 'toko_cost';
    case TaxPayment = 'tax_payment';
    case Adjustment = 'adjustment';
    case Exclude = 'exclude';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material / Bahan',
            self::ProductionCost => 'Biaya Produksi (Gaji Mingguan)',
            self::MarketplaceCost => 'Biaya Marketplace',
            self::TokoCost => 'Biaya Toko',
            self::TaxPayment => 'Pembayaran Pajak',
            self::Adjustment => 'Penyesuaian',
            self::Exclude => 'Exclude from reports',
        };
    }
}
