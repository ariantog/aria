<?php

namespace Database\Seeders;

use App\Support\NewDomainInstall;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            SuperAdminSeeder::class,
            StaffRoleChecklistSeeder::class,
        ]);

        if (NewDomainInstall::allowsBaselineSeed()) {
            $this->call([
                TypicalLedgerSeeder::class,
                AddrbookPlaceholderSeeder::class,
            ]);
        }

        $this->call(DemoDataSeeder::class);
    }
}
