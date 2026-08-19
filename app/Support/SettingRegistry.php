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
            'tutup_buku' => [
                'group' => 'Accounting',
                'name' => 'Tutup Buku',
                'type' => 'tutup_buku',
                'default' => '28',
                'hint' => 'Day of month used as the book-closing cutoff.',
            ],
            'sell_100' => [
                'group' => 'Accounting',
                'name' => 'Account for 100% Discount',
                'type' => 'account',
                'default' => null,
            ],
            'ongkir' => [
                'group' => 'Accounting',
                'name' => 'Account for Ongkir',
                'type' => 'account',
                'default' => null,
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
            'produksi.default_warehouse_id' => [
                'group' => 'Produksi',
                'name' => 'Default Gudang (Warehouse)',
                'type' => 'addrbook_warehouse',
                'default' => null,
                'hint' => 'Default warehouse when sending finished production to stock.',
            ],
            'invoice_maker.terms_of_payment' => [
                'group' => 'Invoice',
                'name' => 'Default Terms of Payment',
                'type' => 'textarea',
                'default' => "Pembayaran lunas sebelum barang dikirim.\nHarga belum termasuk PPN 11%.",
                'hint' => 'Each new line becomes a bullet point on the invoice.',
            ],
            'invoice_maker.pay_to' => [
                'group' => 'Invoice',
                'name' => 'Default Pay To',
                'type' => 'textarea',
                'default' => "BCA\n5105251588\nCV ACTIVEWEAR GLOBAL MANDIRI",
                'hint' => 'Line 1: bank, line 2: account number, line 3: account name.',
            ],
            'invoice_maker.signatory_name' => [
                'group' => 'Invoice',
                'name' => 'Default Signatory Name',
                'type' => 'text',
                'default' => 'Arianto Gunawan',
            ],
            'invoice_maker.default_template' => [
                'group' => 'Invoice',
                'name' => 'Default Invoice Template',
                'type' => 'text',
                'default' => 'classic',
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
