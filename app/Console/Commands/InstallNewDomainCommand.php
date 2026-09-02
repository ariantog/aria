<?php

namespace App\Console\Commands;

use App\Support\NewDomainInstall;
use Database\Seeders\NewDomainSeeder;
use Illuminate\Console\Command;

/**
 * Migrate + seed a brand-new subdomain database.
 *
 * Refuses to run on the current production domain (aria.corenationactive.com
 * or a Crystal production fingerprint). --force skips confirmation only; it
 * never bypasses that guard.
 */
class InstallNewDomainCommand extends Command
{
    protected $signature = 'app:install-new-domain
                            {--skip-migrate : Do not run migrations}
                            {--skip-seed : Do not run NewDomainSeeder}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Run migrations and the new-domain seeder. Refuses on the current production domain.';

    public function handle(): int
    {
        if (! NewDomainInstall::allowsInstall()) {
            $this->error('Refusing to install: '.NewDomainInstall::refusalReason());
            $this->comment('This command is only for empty new-subdomain databases. The current production domain uses individual migrate --path= plus ProductionBootstrapSeeder.');

            return self::FAILURE;
        }

        $host = NewDomainInstall::appHost() ?: '(no host)';
        $database = (string) config('database.connections.'.config('database.default').'.database', '');

        if (! $this->option('force') && ! $this->confirm(
            "Install new-domain schema and baseline on {$host} / {$database}?",
        )) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        if (! $this->option('skip-migrate')) {
            $this->info('Running migrations…');
            $this->call('migrate', ['--force' => true]);
        }

        if (! $this->option('skip-seed')) {
            $this->info('Seeding new-domain baseline…');
            $this->call('db:seed', [
                '--class' => NewDomainSeeder::class,
                '--force' => true,
            ]);
        }

        $this->info('New-domain install complete.');

        return self::SUCCESS;
    }
}
