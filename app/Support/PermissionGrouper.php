<?php

namespace App\Support;

use App\Models\Addrbook;
use App\Models\Borongan;
use App\Models\ChecklistTemplate;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Jubelio;
use App\Models\Karyawan;
use App\Models\Location;
use App\Models\Operation;
use App\Models\Produksi;
use App\Models\Report;
use App\Models\RestockSheet;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Permission;

/**
 * Groups Spatie permissions for the role matrix using each model's getPermissions()
 * registry, with a small fallback for legacy/orphan permission names.
 */
class PermissionGrouper
{
    /** @var array<string, string>|null permission name => group label */
    private static ?array $registry = null;

    /** @var list<string> */
    private const GROUP_ORDER = [
        'Users',
        'Checklist Peran',
        'Roles',
        'Permissions',
        'Locations',
        'Addrbook',
        'Items',
        'Item Groups',
        'Tags',
        'Asset Lancar',
        'Asset Tetap',
        'Transactions',
        'Restock',
        'Reports',
        'Journals — Operations',
        'Journals — Accounts',
        'Production',
        'Production — Workers',
        'Borongan',
        'Karyawan',
        'Karyawan — Gaji',
        'Karyawan — Cuti',
        'Settings',
        'Cron Manager',
        'Jubelio',
        'Other',
    ];

    /**
     * @param  iterable<int, Permission>  $permissions
     * @return array<string, list<Permission>>
     */
    public static function group(iterable $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $group = self::resolveGroup($permission->name);
            $grouped[$group][] = $permission;
        }

        uksort($grouped, function (string $a, string $b): int {
            $order = array_flip(self::GROUP_ORDER);
            $ia = $order[$a] ?? 999;
            $ib = $order[$b] ?? 999;

            if ($ia !== $ib) {
                return $ia <=> $ib;
            }

            return strcasecmp($a, $b);
        });

        return $grouped;
    }

    public static function resolveGroup(string $name): string
    {
        return self::registry()[$name] ?? self::guessGroup($name);
    }

    /**
     * @return array<string, string>
     */
    private static function registry(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        self::$registry = [];

        /** @var list<array{class-string, string, array<string, string>}> */
        $definitions = [
            [User::class, 'Users', [
                'roles-' => 'Roles',
                'permissions-' => 'Permissions',
                'staff-roles-' => 'Users',
            ]],
            [ChecklistTemplate::class, 'Checklist Peran'],
            [Location::class, 'Locations'],
            [Addrbook::class, 'Addrbook'],
            [Item::class, 'Items', [
                'asset-lancar-' => 'Asset Lancar',
                'asset-tetap-' => 'Asset Tetap',
            ]],
            [ItemGroup::class, 'Item Groups'],
            [Tag::class, 'Tags'],
            [Transaction::class, 'Transactions'],
            [RestockSheet::class, 'Restock'],
            [Report::class, 'Reports'],
            [Operation::class, 'Journals — Operations', [
                'account-' => 'Journals — Accounts',
            ]],
            [Produksi::class, 'Production'],
            [Worker::class, 'Production — Workers'],
            [Borongan::class, 'Borongan'],
            [Karyawan::class, 'Karyawan', [
                'gaji-' => 'Karyawan — Gaji',
                'cuti-' => 'Karyawan — Cuti',
            ]],
            [Setting::class, 'Settings'],
            [ScheduledTask::class, 'Cron Manager'],
            [Jubelio::class, 'Jubelio'],
        ];

        foreach ($definitions as $definition) {
            [$class, $defaultGroup] = $definition;
            $prefixMap = $definition[2] ?? [];

            if (! method_exists($class, 'getPermissions')) {
                continue;
            }

            foreach ($class::getPermissions() as $key => $permissionName) {
                $group = $defaultGroup;

                foreach ($prefixMap as $prefix => $prefixGroup) {
                    if (str_starts_with($key, $prefix)) {
                        $group = $prefixGroup;
                        break;
                    }
                }

                self::$registry[$permissionName] = $group;
            }
        }

        return self::$registry;
    }

    private static function guessGroup(string $name): string
    {
        if (str_starts_with($name, 'setting-cron-manager-')) {
            return 'Cron Manager';
        }

        if (str_contains($name, '_')) {
            $module = explode('_', $name)[0];

            return match ($module) {
                'assetLancar' => 'Asset Lancar',
                'assetTetap' => 'Asset Tetap',
                default => ucfirst($module),
            };
        }

        if (str_contains($name, '-')) {
            $module = explode('-', $name)[0];

            return match ($module) {
                'items', 'item' => 'Items',
                'assetTetap' => 'Asset Tetap',
                'stuff' => str_contains($name, '-tag-') ? 'Tags' : 'Item Groups',
                'users' => str_contains($name, '-locations-') ? 'Locations'
                    : (str_contains($name, '-roles-') ? 'Roles'
                    : (str_contains($name, '-permissions-') ? 'Permissions' : 'Users')),
                'checklist' => 'Checklist Peran',
                'addrbook' => 'Addrbook',
                'transactions' => 'Transactions',
                'production' => str_contains($name, '-worker-') ? 'Production — Workers' : 'Production',
                'journal' => str_contains($name, '-account-') ? 'Journals — Accounts' : 'Journals — Operations',
                'report' => 'Reports',
                'setting' => 'Settings',
                'karyawan' => match (true) {
                    str_contains($name, '-gaji-') => 'Karyawan — Gaji',
                    str_contains($name, '-cuti-') => 'Karyawan — Cuti',
                    default => 'Karyawan',
                },
                'borongan' => 'Borongan',
                'restock' => 'Restock',
                'jubelio' => 'Jubelio',
                'shopee-ads' => 'Shopee Ads',
                default => ucfirst($module),
            };
        }

        return 'Other';
    }
}
