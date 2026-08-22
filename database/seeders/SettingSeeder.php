<?php

namespace Database\Seeders;

use App\Support\SettingRegistry;
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

        foreach (SettingRegistry::definitions() as $slug => $definition) {
            $attributes = [
                'group' => $definition['group'],
                'name' => $definition['name'],
                'value' => $definition['default'],
            ];

            if (Schema::hasColumn('settings', 'location_id')) {
                $attributes['location_id'] = 0;
            }

            \App\Models\Setting::updateOrCreate(
                ['slug' => $slug],
                $attributes
            );
        }
    }
}
