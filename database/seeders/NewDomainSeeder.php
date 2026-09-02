<?php

namespace Database\Seeders;

use App\Support\NewDomainInstall;
use Illuminate\Database\Seeder;

/**
 * Main seeder for a brand-new subdomain database.
 *
 * Does not run on the current production domain. Use ProductionBootstrapSeeder
 * there instead. Local preview still uses DatabaseSeeder (this baseline plus
 * DemoDataSeeder).
 *
 *   php artisan db:seed --class=NewDomainSeeder --force
 *   php artisan app:install-new-domain
 */
class NewDomainSeeder extends Seeder
{
    public function run(): void
    {
        if (! NewDomainInstall::allowsBaselineSeed()) {
            $this->command?->error('NewDomainSeeder refused: '.NewDomainInstall::refusalReason());

            return;
        }

        $this->call([
            SuperAdminSeeder::class,
            ProductionBootstrapSeeder::class,
            TypicalLedgerSeeder::class,
            AddrbookPlaceholderSeeder::class,
        ]);
    }
}
