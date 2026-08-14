<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addrbook extends Model
{
    use FillsProductionColumnDefaults, HasFactory, SoftDeletes;

    const TYPE_CUSTOMER = 1;

    const TYPE_WAREHOUSE = 2;

    const TYPE_BANK = 3;

    const TYPE_SUPPLIER = 4;

    const TYPE_V_WAREHOUSE = 5;

    const TYPE_V_ACCOUNT = 6;

    const TYPE_RESELLER = 7;

    const TYPE_ACCOUNT = 8;

    const TYPE_OTHER = 99;

    protected $table = 'customers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'ppn' => 'boolean',
            'is_online' => 'boolean',
            'arrangement_enabled' => 'boolean',
        ];
    }

    public $appends = ['type_name', 'type_slug'];

    public static function typeLabel(int $type): string
    {
        return match ($type) {
            self::TYPE_CUSTOMER => 'Customer',
            self::TYPE_WAREHOUSE => 'Warehouse',
            self::TYPE_BANK => 'Bank (Account)',
            self::TYPE_SUPPLIER => 'Supplier',
            self::TYPE_V_WAREHOUSE => 'V.Warehouse',
            self::TYPE_V_ACCOUNT => 'V.Account',
            self::TYPE_RESELLER => 'Reseller',
            self::TYPE_ACCOUNT => 'Account',
            self::TYPE_OTHER => 'Other',
            default => 'Other',
        };
    }

    public static function typeSlug(int $type): string
    {
        return match ($type) {
            self::TYPE_CUSTOMER => 'customer',
            self::TYPE_WAREHOUSE => 'warehouse',
            self::TYPE_BANK => 'bank',
            self::TYPE_SUPPLIER => 'supplier',
            self::TYPE_V_WAREHOUSE => 'vwarehouse',
            self::TYPE_V_ACCOUNT => 'vaccount',
            self::TYPE_RESELLER => 'reseller',
            self::TYPE_ACCOUNT => 'account',
            self::TYPE_OTHER => 'other',
            default => 'other',
        };
    }

    public static function typeAllowsNegativeStock(int $type): bool
    {
        return $type === self::TYPE_V_WAREHOUSE;
    }

    public static function typeIsWarehouse(int $type): bool
    {
        return in_array($type, [self::TYPE_WAREHOUSE, self::TYPE_V_WAREHOUSE], true);
    }

    public static function typeIsFinancial(int $type): bool
    {
        return in_array($type, [self::TYPE_BANK, self::TYPE_ACCOUNT, self::TYPE_V_ACCOUNT], true);
    }

    /** @return list<int> */
    public static function transferAccountTypes(): array
    {
        return [self::TYPE_BANK, self::TYPE_V_ACCOUNT];
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public static function getTypes(): array
    {
        $ids = [
            self::TYPE_CUSTOMER, self::TYPE_WAREHOUSE, self::TYPE_BANK, self::TYPE_SUPPLIER,
            self::TYPE_V_WAREHOUSE, self::TYPE_V_ACCOUNT, self::TYPE_RESELLER, self::TYPE_ACCOUNT, self::TYPE_OTHER,
        ];

        return array_map(
            fn (int $id) => ['id' => $id, 'name' => self::typeLabel($id), 'slug' => self::typeSlug($id)],
            $ids,
        );
    }

    public static function getPermissions(?string $type = null): array
    {
        $permissions = ['view' => 'addrbook-list', 'create' => 'addrbook-create', 'edit' => 'addrbook-edit', 'delete' => 'addrbook-delete'];
        foreach (self::getTypes() as $t) {
            $cleanName = str_replace(['(', ')', '.', '-', '_'], ' ', $t['name']);
            $kebabName = \Illuminate\Support\Str::kebab(str_replace(' ', '', $cleanName));
            $group = 'addrbook-'.$kebabName;
            $typePermissions = ['view' => "{$group}-list", 'create' => "{$group}-create", 'edit' => "{$group}-edit", 'delete' => "{$group}-delete"];
            if ($t['slug'] === 'bank') {
                $typePermissions['hidden-balance'] = "{$group}-hidden-balance";
            }
            if ($type === $t['slug']) {
                return $typePermissions;
            }
            $permissions["{$t['slug']}-view"] = $typePermissions['view'];
            $permissions["{$t['slug']}-create"] = $typePermissions['create'];
            $permissions["{$t['slug']}-edit"] = $typePermissions['edit'];
            $permissions["{$t['slug']}-delete"] = $typePermissions['delete'];
            if (isset($typePermissions['hidden-balance'])) {
                $permissions["{$t['slug']}-hidden-balance"] = $typePermissions['hidden-balance'];
            }
        }

        return $permissions;
    }

    public function scopeCustomer(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CUSTOMER);
    }

    public function scopeWarehouse(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WAREHOUSE);
    }

    public function scopeAccount(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ACCOUNT);
    }

    public function getTypeNameAttribute(): string
    {
        return self::typeLabel((int) $this->type);
    }

    public function getTypeSlugAttribute(): string
    {
        return self::typeSlug((int) $this->type);
    }

    public function stat()
    {
        return $this->hasOne(AddrbookStat::class, 'customer_id');
    }

    public function dailies()
    {
        return $this->hasMany(AddrbookDaily::class, 'customer_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            Item::class,
            'warehouse_item',
            'warehouse_id',
            'item_id',
        )->withPivot('quantity');
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_customer', 'customer_id', 'location_id');
    }

    public function arrangementSources(): BelongsToMany
    {
        return $this->belongsToMany(
            Addrbook::class,
            'warehouse_arrangement_sources',
            'destination_warehouse_id',
            'source_warehouse_id',
        )->withTimestamps();
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        return app(\App\Services\LocationAccessService::class)->applyAddrbookScope($query, $user);
    }
}
