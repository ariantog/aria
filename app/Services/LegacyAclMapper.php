<?php

namespace App\Services;

/**
 * Maps old ACL dump rows (app_id + action) to Spatie permission names.
 *
 * Used only by the one-time `app:import-legacy-acl` command. After import,
 * authorization is 100% Spatie (permissions / roles / Gate) — no legacy acl table.
 */
class LegacyAclMapper
{
    public const HOME = 0;

    public const USERS = 1;

    public const SETTINGS = 2;

    public const CUSTOMERS = 3;

    public const SUPPLIERS = 4;

    public const WAREHOUSES = 5;

    public const BANKACCOUNTS = 6;

    public const VWAREHOUSES = 7;

    public const ITEMS = 8;

    public const ASSET_LANCAR = 9;

    public const ASSET_TETAP = 10;

    public const TRANSACTIONS = 11;

    public const TAGS = 12;

    public const LOCATIONS = 14;

    public const DELETED = 15;

    public const ACCOUNTS = 16;

    public const REPORTS = 17;

    public const PERSONNELS = 18;

    public const VACCOUNTS = 19;

    public const RESELLERS = 20;

    public const OPERATION = 21;

    public const PELANGGARAN = 24;

    public const CUTI = 25;

    public const GAJI = 26;

    public const HASHTAG = 30;

    public const NOTIFICATION = 31;

    public const VITEM = 32;

    public const HIDE = 501;

    public const PRINTER = 502;

    public const TRANSACTION_FILTERS = 503;

    public const BORONGAN = 203;

    public const APRODUCTIONS = 204;

    public const PRODUKSI = 205;

    public const CONTRIBUTORS = 206;

    public const SETORAN = 207;

    /** @var array<int, string> */
    private const ADDRBOOK_PREFIX = [
        self::CUSTOMERS => 'addrbook-customer',
        self::SUPPLIERS => 'addrbook-supplier',
        self::WAREHOUSES => 'addrbook-warehouse',
        self::BANKACCOUNTS => 'addrbook-bank-account',
        self::VWAREHOUSES => 'addrbook-v-warehouse',
        self::VACCOUNTS => 'addrbook-v-account',
        self::RESELLERS => 'addrbook-reseller',
    ];

    /**
     * @return list<string>
     */
    public function map(int $appId, string $action): array
    {
        return match ($appId) {
            self::USERS => $this->mapUsers($action),
            self::SETTINGS => $this->mapSettings($action),
            self::CUSTOMERS,
            self::SUPPLIERS,
            self::WAREHOUSES,
            self::BANKACCOUNTS,
            self::VWAREHOUSES,
            self::VACCOUNTS,
            self::RESELLERS => $this->mapAddrbook($appId, $action),
            self::ITEMS => $this->mapItems($action),
            self::ASSET_LANCAR => $this->mapAssetLancar($action),
            self::ASSET_TETAP => $this->mapAssetTetap($action),
            self::TRANSACTIONS => $this->mapTransactions($action),
            self::TAGS => $this->mapTags($action),
            self::LOCATIONS => $this->mapLocations($action),
            self::DELETED => $this->mapDeleted($action),
            self::ACCOUNTS, self::OPERATION => $this->mapJournal($appId, $action),
            self::REPORTS => $this->mapReports($action),
            self::PERSONNELS => $this->mapPersonnels($action),
            self::CUTI => $this->mapCuti($action),
            self::GAJI => $this->mapGaji($action),
            self::BORONGAN => $this->mapBorongan($action),
            self::PRODUKSI => $this->mapProduksi($action),
            self::SETORAN => $this->mapSetoran($action),
            self::CONTRIBUTORS => ['report-product-performance'],
            self::HIDE => $this->mapHide($action),
            self::PRINTER => $this->mapPrinter($action),
            self::TRANSACTION_FILTERS => ['transactions-list'],
            self::VITEM => $this->mapItems($action),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapUsers(string $action): array
    {
        return match ($action) {
            'index' => ['users-list'],
            'ban', 'unban' => ['users-edit'],
            'roles' => ['users-roles-list'],
            'create-role' => ['users-roles-create'],
            'edit-role' => ['users-roles-edit'],
            'acl' => ['users-permissions-generate'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapSettings(string $action): array
    {
        return match ($action) {
            'index' => ['setting-general-view'],
            'cron-runner', 'cronrunner' => ['setting-cron-manager-view', 'setting-cron-manager-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapAddrbook(int $appId, string $action): array
    {
        $prefix = self::ADDRBOOK_PREFIX[$appId] ?? null;
        if (! $prefix) {
            return [];
        }

        return match ($action) {
            'index', 'detail', 'transactions', 'stat', 'sales',
            'search-item', 'summary' => ["{$prefix}-list"],
            'item', 'items' => in_array($appId, [self::WAREHOUSES, self::VWAREHOUSES], true)
                ? ["{$prefix}-items"]
                : ["{$prefix}-list"],
            'itemsale' => in_array($appId, [self::BANKACCOUNTS, self::VACCOUNTS], true)
                ? []
                : ["{$prefix}-item-sales"],
            'create' => ["{$prefix}-create"],
            'edit', 'restore' => ["{$prefix}-edit"],
            'delete' => ["{$prefix}-delete"],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapItems(string $action): array
    {
        return match ($action) {
            'index', 'detail', 'price', 'sell-stats', 'use-stats', 'transactions', 'see-tags' => ['items-list'],
            'create' => ['items-create'],
            'edit' => ['items-edit'],
            'group' => ['stuff-group-list'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapAssetLancar(string $action): array
    {
        return match ($action) {
            'index', 'detail', 'transactions' => ['assetLancar-list'],
            'create' => ['assetLancar-create'],
            'edit', 'see-cost' => ['assetLancar-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapAssetTetap(string $action): array
    {
        return match ($action) {
            'index', 'detail', 'transactions' => ['assetTetap-list'],
            'create' => ['assetTetap-create'],
            'edit' => ['assetTetap-edit'],
            'delete' => ['assetTetap-delete'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapTransactions(string $action): array
    {
        return match ($action) {
            'index' => ['transactions-list'],
            'buy' => ['transactions-type-buy'],
            'sell', 'sell-batch' => ['transactions-type-sell'],
            'move', 'move-batch' => ['transactions-type-move'],
            'cash-in' => ['transactions-type-cash-in'],
            'cash-out' => ['transactions-type-cash-out'],
            'transfer' => ['transactions-type-transfer'],
            'adjust' => ['transactions-type-adjust'],
            'return' => ['transactions-type-return'],
            'return-supplier' => ['transactions-type-return-supplier'],
            'use' => ['transactions-list'],
            'delete' => ['transactions-delete'],
            'detail', 'image' => ['transactions-show', 'transactions-transaction-sync'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapTags(string $action): array
    {
        return match ($action) {
            'index', 'detail' => ['stuff-tag-list'],
            'create' => ['stuff-tag-create'],
            'edit' => ['stuff-tag-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapLocations(string $action): array
    {
        return match ($action) {
            'index' => ['users-locations-list'],
            'create' => ['users-locations-create'],
            'edit', 'assign', 'dismiss', 'settings' => ['users-locations-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapDeleted(string $action): array
    {
        return match ($action) {
            'index', 'detail' => ['transactions-list', 'transactions-delete'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapJournal(int $appId, string $action): array
    {
        if ($appId === self::OPERATION) {
            return match ($action) {
                'index', 'detail' => ['journal-operation-list'],
                'create' => ['journal-operation-create'],
                'edit' => ['journal-operation-edit'],
                'accounts', 'account-detail', 'account-hash' => ['journal-account-list'],
                'create-account' => ['journal-account-create'],
                'edit-account' => ['journal-account-edit'],
                default => [],
            };
        }

        return match ($action) {
            'index', 'detail' => ['journal-account-list'],
            'create' => ['journal-account-create'],
            'edit' => ['journal-account-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapReports(string $action): array
    {
        return match ($action) {
            'cash' => ['report-nett-cash'],
            'cash-flow' => ['report-nett-cash'],
            'profit-loss', 'revenue', 'aspc', 'customer-class', 'geo', 'balance' => ['report-laba-rugi'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapPersonnels(string $action): array
    {
        return match ($action) {
            'index', 'gpu', 'private-gpu' => ['karyawan-list', 'production-worker-list'],
            'create' => ['karyawan-create', 'production-worker-create'],
            'edit', 'edit-gpu', 'edit-cuti', 'delete', 'restore' => ['karyawan-edit', 'production-worker-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapCuti(string $action): array
    {
        return match ($action) {
            'index', 'settings' => ['karyawan-cuti-list'],
            'add' => ['karyawan-cuti-create'],
            'edit' => ['karyawan-cuti-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapGaji(string $action): array
    {
        return match ($action) {
            'index', 'private' => ['karyawan-gaji-list'],
            'generate' => ['karyawan-gaji-create'],
            'edit' => ['karyawan-gaji-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapBorongan(string $action): array
    {
        return match ($action) {
            'index', 'load', 'stat' => ['borongan-list'],
            'add' => ['borongan-create'],
            'detail' => ['borongan-view'],
            'edit' => ['borongan-edit'],
            'delete' => ['borongan-delete'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapProduksi(string $action): array
    {
        return match ($action) {
            'index', 'jahit-list', 'potong-list' => ['production-list'],
            'create-produksi', 'create-jahit', 'create-potong' => ['production-create', 'production-worker-create'],
            'edit', 'edit-produksi', 'edit-jahit', 'edit-potong', 'save-row', 'ganti-jahit',
            'pisah-jahit', 'restore-jahit', 'restore-potong', 'delete-jahit', 'delete-potong' => ['production-edit', 'production-worker-edit'],
            'setor' => ['production-setor'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapSetoran(string $action): array
    {
        return match ($action) {
            'index', 'edit-item', 'edit-jahit', 'edit' => ['production-setoran-list'],
            'edit-status' => ['production-setoran-revert'],
            'gudang' => ['production-gudang'],
            'delete' => ['production-edit'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapPrinter(string $action): array
    {
        return match ($action) {
            'transaction' => ['transactions-transaction-sync', 'transactions-show'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function mapHide(string $action): array
    {
        return match ($action) {
            'balance' => ['addrbook-bank-account-hidden-balance'],
            default => [],
        };
    }
}
