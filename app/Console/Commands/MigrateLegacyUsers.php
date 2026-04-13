<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate users and roles from legacy database to core-aria';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting migration of users and roles...');

        try {
            Schema::disableForeignKeyConstraints();

            // 1. Clear existing data
            $this->warn('Clearing existing users, roles, and locations...');
            DB::table('users')->truncate();
            DB::table('roles')->truncate();
            DB::table('locations')->truncate();

            // 2. Migrate Locations
            $this->info('Migrating locations...');
            $legacyLocations = DB::connection('core_legacy')->table('locations')->get();
            foreach ($legacyLocations as $location) {
                DB::table('locations')->insert([
                    'id' => $location->id,
                    'name' => $location->name,
                    'created_at' => $this->sanitizeDate($location->created_at),
                    'updated_at' => $this->sanitizeDate($location->updated_at),
                ]);
            }
            $this->info("Migrated {$legacyLocations->count()} locations.");

            // 3. Migrate Roles
            $this->info('Migrating roles...');
            $legacyRoles = DB::connection('core_legacy')->table('roles')->get();
            foreach ($legacyRoles as $role) {
                DB::table('roles')->insert([
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name ?? 'web',
                    'created_at' => $this->sanitizeDate($role->created_at),
                    'updated_at' => $this->sanitizeDate($role->updated_at),
                ]);
            }
            $this->info("Migrated {$legacyRoles->count()} roles.");

            // 4. Migrate Users
            $this->info('Migrating users...');
            $legacyUsers = DB::connection('core_legacy')->table('users')->get();
            foreach ($legacyUsers as $user) {
                DB::table('users')->insert([
                    'id' => $user->id,
                    'name' => $user->username,
                    'username' => $user->username,
                    'email' => $user->username.'@mail.com', // Dummy email to satisfy schema
                    'password' => $user->password,
                    'is_active' => (bool) $user->active,
                    'location_id' => $user->location_id ?: null,
                    'remember_token' => $user->remember_token,
                    'created_at' => $this->sanitizeDate($user->created_at),
                    'updated_at' => $this->sanitizeDate($user->updated_at),
                ]);
            }
            $this->info("Migrated {$legacyUsers->count()} users.");

            Schema::enableForeignKeyConstraints();
            $this->info('Migration completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Migration failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Sanitize legacy dates to avoid "0000-00-00" errors.
     */
    private function sanitizeDate(?string $date): ?string
    {
        if (! $date || $date === '0000-00-00 00:00:00' || $date === '0000-00-00') {
            return now()->toDateTimeString();
        }

        return $date;
    }
}
