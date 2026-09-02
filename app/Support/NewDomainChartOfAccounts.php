<?php

namespace App\Support;

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;

/**
 * Starter chart of accounts for a new subdomain (not the Crystal production IDs).
 *
 * @phpstan-type PlaceholderRow array{type: int, name: string, description: string, ppn?: bool}
 * @phpstan-type OperationRow array{name: string, report_slug: string, description: string}
 * @phpstan-type LedgerRow array{name: string, operation: string, hint: string, description: string, role?: string}
 */
final class NewDomainChartOfAccounts
{
    /** @return list<PlaceholderRow> */
    public static function placeholders(): array
    {
        return [
            [
                'type' => Addrbook::TYPE_CUSTOMER,
                'name' => 'Pelanggan',
                'description' => 'Placeholder customer. Rename or replace after go-live.',
            ],
            [
                'type' => Addrbook::TYPE_WAREHOUSE,
                'name' => 'Gudang',
                'description' => 'Placeholder warehouse for buy / sell / move.',
            ],
            [
                'type' => Addrbook::TYPE_BANK,
                'name' => 'Kas / Bank',
                'description' => 'Placeholder cash / bank account for cash in, cash out, and transfer.',
            ],
            [
                'type' => Addrbook::TYPE_SUPPLIER,
                'name' => 'Supplier',
                'description' => 'Placeholder supplier for purchase and return-supplier.',
                'ppn' => true,
            ],
            [
                'type' => Addrbook::TYPE_V_WAREHOUSE,
                'name' => 'Gudang Virtual',
                'description' => 'Placeholder virtual warehouse (negative stock allowed).',
            ],
            [
                'type' => Addrbook::TYPE_V_ACCOUNT,
                'name' => 'Akun Virtual',
                'description' => 'Placeholder virtual account for transfers.',
            ],
            [
                'type' => Addrbook::TYPE_RESELLER,
                'name' => 'Reseller',
                'description' => 'Placeholder reseller contact.',
            ],
            [
                'type' => Addrbook::TYPE_ACCOUNT,
                'name' => 'Akun Umum',
                'description' => 'Placeholder expense account. Typical ledgers live under operations.',
            ],
            [
                'type' => Addrbook::TYPE_OTHER,
                'name' => 'Lainnya',
                'description' => 'Placeholder other contact.',
            ],
        ];
    }

    /** @return list<OperationRow> */
    public static function operations(): array
    {
        return [
            ['name' => 'Biaya Marketplace', 'report_slug' => 'marketplace', 'description' => 'Online channel and marketplace fees'],
            ['name' => 'Biaya Toko', 'report_slug' => 'toko', 'description' => 'Physical shop upkeep'],
            ['name' => 'Marketing Umum', 'report_slug' => 'marketing', 'description' => 'Non-channel marketing: iklan, pameran, promosi'],
            ['name' => 'Gaji & Upah', 'report_slug' => 'gaji', 'description' => 'Payroll'],
            ['name' => 'Produksi', 'report_slug' => 'produksi', 'description' => 'Material, biaya produksi, perlengkapan'],
            ['name' => 'Logistik', 'report_slug' => 'logistik', 'description' => 'Shipping, bensin, ongkir'],
            ['name' => 'Kantor & Utilitas', 'report_slug' => 'kantor', 'description' => 'Office, utilities, subscriptions'],
            ['name' => 'Perawatan & Mesin', 'report_slug' => 'maintenance', 'description' => 'Repair and maintenance'],
            ['name' => 'Jasa Profesional', 'report_slug' => 'jasa', 'description' => 'Consultants and professional fees'],
            ['name' => 'Kesejahteraan Karyawan', 'report_slug' => 'sdm', 'description' => 'Staff welfare'],
            ['name' => 'Pajak & Retribusi', 'report_slug' => 'pajak', 'description' => 'Generic tax (SSP, PBB)'],
            ['name' => 'Perbankan', 'report_slug' => 'bank', 'description' => 'Bank fees'],
            ['name' => 'Penyesuaian', 'report_slug' => 'penyesuaian', 'description' => 'Adjustments, rounding, write-offs'],
            ['name' => 'Lain-lain', 'report_slug' => 'lain', 'description' => 'Miscellaneous'],
            ['name' => 'Sewa HQ', 'report_slug' => 'sewa', 'description' => 'Office / HQ rent'],
        ];
    }

    /** @return list<LedgerRow> */
    public static function ledgers(): array
    {
        return [
            [
                'name' => 'Biaya Shopee',
                'operation' => 'Biaya Marketplace',
                'hint' => 'Komisi, iklan, dan biaya platform Shopee.',
                'description' => 'Biaya channel Shopee',
                'role' => ReportingLedgerRole::MarketplaceCost->value,
            ],
            [
                'name' => 'Biaya TikTok',
                'operation' => 'Biaya Marketplace',
                'hint' => 'Komisi, iklan, dan biaya platform TikTok Shop.',
                'description' => 'Biaya channel TikTok Shop',
                'role' => ReportingLedgerRole::MarketplaceCost->value,
            ],
            [
                'name' => 'Biaya Lazada',
                'operation' => 'Biaya Marketplace',
                'hint' => 'Komisi dan biaya platform Lazada.',
                'description' => 'Biaya channel Lazada',
                'role' => ReportingLedgerRole::MarketplaceCost->value,
            ],
            [
                'name' => 'Biaya Tokopedia',
                'operation' => 'Biaya Marketplace',
                'hint' => 'Komisi dan biaya platform Tokopedia.',
                'description' => 'Biaya channel Tokopedia',
                'role' => ReportingLedgerRole::MarketplaceCost->value,
            ],
            [
                'name' => 'Biaya Marketing Digital',
                'operation' => 'Biaya Marketplace',
                'hint' => 'Social media, kolaborasi, influencer — bukan komisi marketplace.',
                'description' => 'Marketing digital non-platform',
            ],
            [
                'name' => 'Biaya Toko',
                'operation' => 'Biaya Toko',
                'hint' => 'Semua biaya toko: sewa, utilitas, transport, perlengkapan. Isi catatan untuk detail.',
                'description' => 'Biaya operasional toko',
                'role' => ReportingLedgerRole::TokoCost->value,
            ],
            [
                'name' => 'Biaya Iklan',
                'operation' => 'Marketing Umum',
                'hint' => 'Iklan offline / umum, bukan iklan marketplace.',
                'description' => 'Iklan umum',
            ],
            [
                'name' => 'Biaya Pameran',
                'operation' => 'Marketing Umum',
                'hint' => 'Booth, event, dan pameran.',
                'description' => 'Pameran',
            ],
            [
                'name' => 'Biaya Promosi',
                'operation' => 'Marketing Umum',
                'hint' => 'Promosi, katalog, banner, sample marketing.',
                'description' => 'Promosi',
            ],
            [
                'name' => 'Gaji Bulanan',
                'operation' => 'Gaji & Upah',
                'hint' => 'Gaji tetap bulanan (bukan gaji mingguan produksi).',
                'description' => 'Gaji bulanan',
            ],
            [
                'name' => 'Gaji Mingguan',
                'operation' => 'Gaji & Upah',
                'hint' => 'Gaji mingguan produksi — dihitung sebagai biaya produksi di laporan.',
                'description' => 'Gaji mingguan produksi',
                'role' => ReportingLedgerRole::ProductionCost->value,
            ],
            [
                'name' => 'Bonus & Insentif',
                'operation' => 'Gaji & Upah',
                'hint' => 'Bonus, insentif, lembur, THR.',
                'description' => 'Bonus dan insentif',
            ],
            [
                'name' => 'Material Produksi',
                'operation' => 'Produksi',
                'hint' => 'Pembelian bahan baku / material produksi.',
                'description' => 'Bahan baku',
                'role' => ReportingLedgerRole::Material->value,
            ],
            [
                'name' => 'Biaya Produksi',
                'operation' => 'Produksi',
                'hint' => 'Biaya produksi yang tidak terukur per SKU.',
                'description' => 'Biaya produksi lain',
            ],
            [
                'name' => 'Perlengkapan Produksi',
                'operation' => 'Produksi',
                'hint' => 'Aksesoris mesin, spare part, perlengkapan jahit.',
                'description' => 'Perlengkapan produksi',
            ],
            [
                'name' => 'Biaya Ongkir',
                'operation' => 'Logistik',
                'hint' => 'Ongkos kirim HQ / umum, bukan biaya toko.',
                'description' => 'Ongkir',
            ],
            [
                'name' => 'Biaya Bensin',
                'operation' => 'Logistik',
                'hint' => 'Bensin, tol, transport logistik.',
                'description' => 'Bensin dan transport',
            ],
            [
                'name' => 'Biaya Listrik',
                'operation' => 'Kantor & Utilitas',
                'hint' => 'Listrik kantor / gudang (bukan toko).',
                'description' => 'Listrik',
            ],
            [
                'name' => 'Biaya Internet',
                'operation' => 'Kantor & Utilitas',
                'hint' => 'Internet, telepon, langganan software.',
                'description' => 'Internet dan langganan',
            ],
            [
                'name' => 'ATK',
                'operation' => 'Kantor & Utilitas',
                'hint' => 'Alat tulis dan perlengkapan kantor.',
                'description' => 'ATK',
            ],
            [
                'name' => 'Biaya Asuransi',
                'operation' => 'Kantor & Utilitas',
                'hint' => 'Asuransi gedung, kendaraan, atau barang.',
                'description' => 'Asuransi',
            ],
            [
                'name' => 'Biaya Perawatan',
                'operation' => 'Perawatan & Mesin',
                'hint' => 'Servis mesin, perbaikan gedung, maintenance.',
                'description' => 'Perawatan dan mesin',
            ],
            [
                'name' => 'Biaya Konsultan',
                'operation' => 'Jasa Profesional',
                'hint' => 'Konsultan, legal, audit, jasa profesional.',
                'description' => 'Jasa profesional',
            ],
            [
                'name' => 'Biaya Kesehatan',
                'operation' => 'Kesejahteraan Karyawan',
                'hint' => 'Kesehatan, BPJS di luar gaji, kesejahteraan.',
                'description' => 'Kesehatan karyawan',
            ],
            [
                'name' => 'Training',
                'operation' => 'Kesejahteraan Karyawan',
                'hint' => 'Pelatihan dan training karyawan.',
                'description' => 'Training',
            ],
            [
                'name' => 'SSP',
                'operation' => 'Pajak & Retribusi',
                'hint' => 'Setoran pajak generic (bukan PPh per entitas).',
                'description' => 'SSP',
                'role' => ReportingLedgerRole::TaxPayment->value,
            ],
            [
                'name' => 'PBB',
                'operation' => 'Pajak & Retribusi',
                'hint' => 'Pajak bumi dan bangunan.',
                'description' => 'PBB',
                'role' => ReportingLedgerRole::TaxPayment->value,
            ],
            [
                'name' => 'Biaya Administrasi Bank',
                'operation' => 'Perbankan',
                'hint' => 'Admin bank, transfer fee, materai rekening.',
                'description' => 'Biaya bank',
            ],
            [
                'name' => 'Pembulatan',
                'operation' => 'Penyesuaian',
                'hint' => 'Pembulatan kasir / selisih kecil.',
                'description' => 'Pembulatan',
                'role' => ReportingLedgerRole::Adjustment->value,
            ],
            [
                'name' => 'Penyesuaian Umum',
                'operation' => 'Penyesuaian',
                'hint' => 'Penyesuaian, write-off, koreksi saldo.',
                'description' => 'Penyesuaian umum',
                'role' => ReportingLedgerRole::Adjustment->value,
            ],
            [
                'name' => 'Biaya Lain-lain',
                'operation' => 'Lain-lain',
                'hint' => 'Pengeluaran yang tidak masuk kategori lain. Minimalkan pemakaian.',
                'description' => 'Lain-lain',
            ],
            [
                'name' => 'Biaya Sewa Kantor',
                'operation' => 'Sewa HQ',
                'hint' => 'Sewa kantor / HQ, bukan sewa toko.',
                'description' => 'Sewa kantor',
            ],
        ];
    }
}
