<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class StockIntelligenceSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'group' => 'stock_intelligence',
                'name' => 'Gap Weight',
                'slug' => 'si_gap_weight',
                'value' => 0.2,
            ],
            [
                'group' => 'stock_intelligence',
                'name' => 'Sale Weight',
                'slug' => 'si_sale_weight',
                'value' => 0.8,
            ],
            [
                'group' => 'stock_intelligence',
                'name' => 'Max Gap',
                'slug' => 'si_max_gap',
                'value' => 90,
            ],
            [
                'group' => 'stock_intelligence',
                'name' => 'Max Days',
                'slug' => 'si_max_days',
                'value' => 90,
            ],
            [
                'group' => 'stock_intelligence',
                'name' => 'Total Rows',
                'slug' => 'si_total_rows',
                'value' => 1000,
            ],
            [
                'group' => 'stock_intelligence',
                'name' => 'Generate Days',
                'slug' => 'si_generate_days',
                'value' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
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
