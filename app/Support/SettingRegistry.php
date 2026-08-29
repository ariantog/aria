<?php

namespace App\Support;

class SettingRegistry
{
    /**
     * Legacy L10 per-location settings — removed on cleanup migration.
     *
     * @var list<string>
     */
    public const LEGACY_SLUGS = [
        'start_time',
        'stop_time',
        'bottom_line',
        'sell_100',
        'ongkir',
        'tutup_buku',
    ];

    /**
     * System-managed slugs kept in DB but hidden from the settings UI.
     *
     * @var list<string>
     */
    public const SYSTEM_SLUGS = [
        'jubelio_token',
        'invoice_logo_path',
        'invoice_signature_path',
        'invoice_maker.presets',
        'invoice_maker.default_preset_id',
    ];

    /**
     * @return array<string, array{group: string, name: string, type: string, default: mixed, hint?: string}>
     */
    public static function definitions(): array
    {
        return [
            'ppn_rate' => [
                'group' => 'Accounting',
                'name' => 'PPN Rate',
                'type' => 'number',
                'default' => '11',
                'hint' => 'VAT percentage applied to taxable transaction lines.',
            ],
            'batas_cuti_tahunan' => [
                'group' => 'HR',
                'name' => 'Batas Cuti Tahunan',
                'type' => 'number',
                'default' => '12',
            ],
            'batas_cuti_sakit' => [
                'group' => 'HR',
                'name' => 'Batas Cuti Sakit',
                'type' => 'number',
                'default' => '30',
            ],
            'payroll.grace_period_menit' => [
                'group' => 'HR',
                'name' => 'Grace Period Telat (menit)',
                'type' => 'number',
                'default' => '15',
                'hint' => 'Menit keterlambatan yang tidak kena potong (default perusahaan). Bisa dioverride per karyawan.',
            ],
            'payroll.jam_kerja_per_hari' => [
                'group' => 'HR',
                'name' => 'Jam Kerja per Hari',
                'type' => 'number',
                'default' => '8',
                'hint' => 'Digunakan untuk hitung tarif per jam (telat & lembur).',
            ],
            'payroll.lembur_multiplier' => [
                'group' => 'HR',
                'name' => 'Pengali Lembur',
                'type' => 'number',
                'default' => '1.5',
                'hint' => 'Upah lembur = jam × (harian ÷ jam kerja) × pengali.',
            ],
            'restock.default_supplier_id' => [
                'group' => 'Restock',
                'name' => 'Default Supplier',
                'type' => 'addrbook_supplier',
                'default' => null,
                'hint' => 'Sender on Buy transactions when receiving shipped stock.',
            ],
            'restock.default_receiver_id' => [
                'group' => 'Restock',
                'name' => 'Default Receiver (Warehouse)',
                'type' => 'addrbook_warehouse',
                'default' => null,
                'hint' => 'Warehouse that receives stock on Buy transactions from receive.',
            ],
            'restock.default_warehouse_ids' => [
                'group' => 'Restock',
                'name' => 'Stock Display Warehouses',
                'type' => 'warehouse_ids',
                'default' => [],
                'hint' => 'Stock column on the restock sheet sums qty from these warehouses only. Leave all unchecked to sum every warehouse.',
            ],
            'asset_tetap.depreciation_expense_account_id' => [
                'group' => 'Accounting',
                'name' => 'Akun Beban Penyusutan',
                'type' => 'account',
                'default' => null,
                'hint' => 'Sender on monthly depreciation (type 18) transactions.',
            ],
            'asset_tetap.depreciation_contra_account_id' => [
                'group' => 'Accounting',
                'name' => 'Akun Akumulasi Penyusutan',
                'type' => 'account',
                'default' => null,
                'hint' => 'Receiver on monthly depreciation (type 18) transactions.',
            ],
            'produksi.default_warehouse_id' => [
                'group' => 'Produksi',
                'name' => 'Default Gudang (Warehouse)',
                'type' => 'addrbook_warehouse',
                'default' => null,
                'hint' => 'Default warehouse when sending finished production to stock.',
            ],
            'reporting.persediaan_awal' => [
                'group' => 'Reporting',
                'name' => 'Persediaan Awal (Jan 2026)',
                'type' => 'number',
                'default' => 0,
                'hint' => 'Opening inventory value for the January 2026 roll-forward. Later months open from the previous closing. Manufactured HPP uses gudang pcs + borongan + Material Produksi.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, array{group: string, name: string, type: string, default: mixed, hint?: string}>|null
     */
    public static function definition(string $slug): ?array
    {
        return self::definitions()[$slug] ?? null;
    }

    public static function isManaged(string $slug): bool
    {
        return isset(self::definitions()[$slug]);
    }

    public static function isLegacy(string $slug): bool
    {
        return in_array($slug, self::LEGACY_SLUGS, true);
    }

    public static function isSystem(string $slug): bool
    {
        return in_array($slug, self::SYSTEM_SLUGS, true);
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return collect(self::definitions())
            ->pluck('group')
            ->unique()
            ->values()
            ->all();
    }
}
