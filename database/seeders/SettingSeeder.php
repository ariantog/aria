<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (! Schema::hasColumn('settings', 'slug')) {
            $this->command?->warn(
                'settings.slug missing — run: php artisan migrate '
                .'--path=database/migrations/2026_08_12_210000_align_settings_table_for_l12.php --force'
            );

            return;
        }

        $settings = [
            [
                'group' => 'Accounting',
                'name' => 'PPN Rate',
                'slug' => 'ppn_rate',
                'value' => '11',
            ],
            [
                'group' => 'Accounting',
                'name' => 'Tutup Buku',
                'slug' => 'tutup_buku',
                'value' => '28',
            ],
            [
                'group' => 'Accounting',
                'name' => 'Account for 100% Discount',
                'slug' => 'sell_100',
                'value' => null,
            ],
            [
                'group' => 'Accounting',
                'name' => 'Account for Ongkir',
                'slug' => 'ongkir',
                'value' => null,
            ],
            [
                'group' => 'HR',
                'name' => 'Batas Cuti Tahunan',
                'slug' => 'batas_cuti_tahunan',
                'value' => '12',
            ],
            [
                'group' => 'HR',
                'name' => 'Batas Cuti Sakit',
                'slug' => 'batas_cuti_sakit',
                'value' => '30',
            ],
            [
                'group' => 'Restock',
                'name' => 'Default Supplier',
                'slug' => 'restock.default_supplier_id',
                'value' => null,
            ],
            [
                'group' => 'Restock',
                'name' => 'Default Receiver (Warehouse)',
                'slug' => 'restock.default_receiver_id',
                'value' => null,
            ],
            [
                'group' => 'Restock',
                'name' => 'Stock Display Warehouses',
                'slug' => 'restock.default_warehouse_ids',
                'value' => [],
            ],
            [
                'group' => 'Produksi',
                'name' => 'Default Gudang (Warehouse)',
                'slug' => 'produksi.default_warehouse_id',
                'value' => null,
            ],
            [
                'group' => 'Invoice',
                'name' => 'Invoice Company Name',
                'slug' => 'invoice_company_name',
                'value' => 'CORENATION',
            ],
            [
                'group' => 'Invoice',
                'name' => 'Invoice Address',
                'slug' => 'invoice_address',
                'value' => 'CILANDAK TOWN SQUARE no.171',
            ],
            [
                'group' => 'Invoice',
                'name' => 'Invoice Phone',
                'slug' => 'invoice_phone',
                'value' => '082244226656',
            ],
        ];

        foreach ($settings as $setting) {
            $attributes = [
                'group' => $setting['group'],
                'name' => $setting['name'],
                'value' => $setting['value'],
            ];

            if (Schema::hasColumn('settings', 'location_id')) {
                $attributes['location_id'] = 0;
            }

            \App\Models\Setting::updateOrCreate(
                ['slug' => $setting['slug']],
                $attributes
            );
        }
    }
}
