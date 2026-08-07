<?php

namespace App\Console\Commands;

use App\Services\LegacyAclMapper;
use App\Services\LegacySqlParser;
use App\Services\PermissionGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ImportLegacyAcl extends Command
{
    protected $signature = 'app:import-legacy-acl
                            {--file=database/acl/old_acl.sql : Path to the legacy ACL SQL dump}
                            {--dry-run : Parse and report without writing}
                            {--skip-users : Do not import users}
                            {--skip-roles : Do not import roles}
                            {--skip-locations : Do not import locations or addrbook_location pivot}
                            {--preserve-user-1 : Skip overwriting user id 1}';

    protected $description = 'One-time import: convert legacy ACL SQL dump into Spatie permissions, roles, users, and location links';

    public function handle(
        LegacySqlParser $parser,
        LegacyAclMapper $mapper,
        PermissionGenerator $permissionGenerator,
    ): int {
        $path = base_path($this->option('file'));

        if (! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return self::FAILURE;
        }

        $this->info("Parsing {$path}...");
        $data = $parser->parseFile($path);

        $this->table(
            ['Dataset', 'Rows'],
            [
                ['acl', count($data['acl'])],
                ['roles', count($data['roles'])],
                ['users', count($data['users'])],
                ['locations', count($data['locations'])],
                ['location_customer', count($data['location_customer'])],
            ]
        );

        $rolePermissions = $this->buildRolePermissions($data['acl'], $mapper);
        $unmapped = $this->countUnmappedAcl($data['acl'], $mapper);

        $this->info('Mapped '.count($rolePermissions).' roles with permissions.');
        if ($unmapped > 0) {
            $this->warn("{$unmapped} ACL rows had no matching new permission (skipped).");
        }

        if ($this->option('dry-run')) {
            foreach ($rolePermissions as $roleId => $permissions) {
                $this->line("Role {$roleId}: ".count($permissions).' permissions');
            }

            return self::SUCCESS;
        }

        $permissionGenerator->generateAll();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        try {
            Schema::disableForeignKeyConstraints();
            DB::beginTransaction();

            if (! $this->option('skip-locations')) {
                $this->importLocations($data['locations']);
                $this->importAddrbookLocations($data['location_customer']);
            }

            if (! $this->option('skip-roles')) {
                $this->importRoles($data['roles']);
            }

            if (! $this->option('skip-users')) {
                $this->importUsers($data['users']);
            }

            $this->syncRolePermissions($rolePermissions);
            $this->assignUserRoles($data['users']);

            DB::commit();
            Schema::enableForeignKeyConstraints();

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $this->info('Legacy ACL import completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  list<array{role_id: int, action: string, app_id: int}>  $aclRows
     * @return array<int, list<string>>
     */
    private function buildRolePermissions(array $aclRows, LegacyAclMapper $mapper): array
    {
        $rolePermissions = [];

        foreach ($aclRows as $row) {
            $mapped = $mapper->map($row['app_id'], $row['action']);
            if ($mapped === []) {
                continue;
            }

            $roleId = $row['role_id'];
            $rolePermissions[$roleId] ??= [];
            foreach ($mapped as $permission) {
                $rolePermissions[$roleId][$permission] = $permission;
            }
        }

        foreach ($rolePermissions as $roleId => $permissions) {
            $rolePermissions[$roleId] = array_values($permissions);
        }

        return $rolePermissions;
    }

    /**
     * @param  list<array{role_id: int, action: string, app_id: int}>  $aclRows
     */
    private function countUnmappedAcl(array $aclRows, LegacyAclMapper $mapper): int
    {
        $count = 0;
        foreach ($aclRows as $row) {
            if ($mapper->map($row['app_id'], $row['action']) === []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array{id: int, name: string}>  $locations
     */
    private function importLocations(array $locations): void
    {
        $this->info('Importing locations...');

        foreach ($locations as $location) {
            DB::table('locations')->updateOrInsert(
                ['id' => $location['id']],
                [
                    'name' => $location['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  list<array{location_id: int, customer_id: int}>  $rows
     */
    private function importAddrbookLocations(array $rows): void
    {
        $this->info('Importing addrbook_location pivot...');

        DB::table('addrbook_location')->truncate();

        $batch = [];
        foreach ($rows as $row) {
            $batch[] = [
                'location_id' => $row['location_id'],
                'addrbook_id' => $row['customer_id'],
            ];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('addrbook_location')->insert($chunk);
        }
    }

    /**
     * @param  list<array{id: int, name: string}>  $roles
     */
    private function importRoles(array $roles): void
    {
        $this->info('Importing roles...');

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  list<array{id: int, username: string, password: string, active: int, role_id: int, location_id: int}>  $users
     */
    private function importUsers(array $users): void
    {
        $this->info('Importing users...');

        foreach ($users as $user) {
            if ($this->option('preserve-user-1') && $user['id'] === 1) {
                continue;
            }

            DB::table('users')->updateOrInsert(
                ['id' => $user['id']],
                [
                    'name' => $user['username'],
                    'username' => $user['username'],
                    'email' => $user['username'].'@mail.com',
                    'password' => $user['password'],
                    'is_active' => (bool) $user['active'],
                    'location_id' => $user['location_id'] > 0 ? $user['location_id'] : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  array<int, list<string>>  $rolePermissions
     */
    private function syncRolePermissions(array $rolePermissions): void
    {
        $this->info('Syncing role permissions...');

        foreach ($rolePermissions as $roleId => $permissionNames) {
            $role = Role::find($roleId);
            if (! $role) {
                $this->warn("Role id {$roleId} not found — skipping permissions.");

                continue;
            }

            $existing = Permission::whereIn('name', $permissionNames)->pluck('name')->all();
            $role->syncPermissions($existing);
        }
    }

    /**
     * @param  list<array{id: int, username: string, password: string, active: int, role_id: int, location_id: int}>  $users
     */
    private function assignUserRoles(array $users): void
    {
        $this->info('Assigning user roles...');

        foreach ($users as $user) {
            if ($user['role_id'] <= 0) {
                continue;
            }

            if ($this->option('preserve-user-1') && $user['id'] === 1) {
                continue;
            }

            $role = Role::find($user['role_id']);
            if (! $role) {
                $this->warn("Role id {$user['role_id']} not found for user {$user['username']}.");

                continue;
            }

            $userModel = \App\Models\User::find($user['id']);
            if ($userModel) {
                $userModel->syncRoles([$role->name]);
            }
        }
    }
}
