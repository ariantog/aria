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
            // [
            //     'group' => 'Inventory',
            //     'name' => 'Default Warehouse',
            //     'slug' => 'default_warehouse_id',
            //     'value' => '1',
            // ],
            // [
            //     'group' => 'System',
            //     'name' => 'Application Name',
            //     'slug' => 'app_name',
            //     'value' => 'Core Aria',
            // ],
            // [
            //     'group' => 'Inventory',
            //     'name' => 'Low Stock Threshold',
            //     'slug' => 'low_stock_threshold',
            //     'value' => '10',
            // ],
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
