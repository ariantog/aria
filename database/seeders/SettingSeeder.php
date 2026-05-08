<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::updateOrCreate(
                ['slug' => $setting['slug']],
                [
                    'group' => $setting['group'],
                    'name' => $setting['name'],
                    'value' => $setting['value'],
                ]
            );
        }
    }
}
