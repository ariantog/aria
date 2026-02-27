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
        \App\Models\Setting::updateOrCreate(
            ['slug' => 'ppn_rate'],
            ['name' => 'PPN Rate', 'value' => '11']
        );
    }
}
