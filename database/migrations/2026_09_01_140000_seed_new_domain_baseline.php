<?php

use App\Support\NewDomainInstall;
use Database\Seeders\NewDomainSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Baseline data for a brand-new subdomain database.
 *
 * No-op on the current production domain (Crystal fingerprint or
 * aria.corenationactive.com) and during automated tests so RefreshDatabase
 * stays empty. On an empty new-subdomain `php artisan migrate` this seeds
 * SuperAdmin, permissions, typical operations/ledgers, and one placeholder
 * per addrbook type.
 *
 * Current production must keep using individual `migrate --path=` plus
 * ProductionBootstrapSeeder — running this file there is a documented no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (NewDomainInstall::isCurrentProductionDomain()) {
            return;
        }

        Artisan::call('db:seed', [
            '--class' => NewDomainSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Keep baseline contacts; do not delete on rollback.
    }
};
