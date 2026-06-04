<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addrbook extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_CUSTOMER = 1;

    const TYPE_WAREHOUSE = 2;

    const TYPE_BANK = 3;

    const TYPE_SUPPLIER = 4;

    const TYPE_V_WAREHOUSE = 5;

    const TYPE_V_ACCOUNT = 6;

    const TYPE_RESELLER = 7;

    const TYPE_ACCOUNT = 8;

    const TYPE_OTHER = 99;

    protected $table = 'addrbooks';

    protected $guarded = ['id'];

    public static function getPermissions(?string $type = null): array
    {
        // 1. Initial shared permissions
        $permissions = [
            'view' => 'addrbook-list',
            'create' => 'addrbook-create',
            'edit' => 'addrbook-edit',
            'delete' => 'addrbook-delete',
        ];

        // 2. Generate per-type permissions
        foreach (self::getTypes() as $t) {
            // Reconstruct Group Name (prefix + kebab-case Name)
            $cleanName = str_replace(['(', ')', '.', '-', '_'], ' ', $t['name']);
            $kebabName = \Illuminate\Support\Str::kebab(str_replace(' ', '', $cleanName));
            $group = 'addrbook-'.$kebabName;

            $typePermissions = [
                'view' => "{$group}-list",
                'create' => "{$group}-create",
                'edit' => "{$group}-edit",
                'delete' => "{$group}-delete",
            ];

            // Add hidden balance permission specifically for bank type
            if ($t['slug'] === 'bank') {
                $typePermissions['hidden-balance'] = "{$group}-hidden-balance";
            }

            // If a specific type slug was requested, return its group permissions
            if ($type === $t['slug']) {
                return $typePermissions;
            }

            // Otherwise, add them to the main map using slug-based keys
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

    public static function getTypes(): array
    {
        return [
            ['id' => self::TYPE_CUSTOMER, 'name' => 'Customer', 'slug' => 'customer'],
            ['id' => self::TYPE_RESELLER, 'name' => 'Reseller', 'slug' => 'reseller'],
            ['id' => self::TYPE_SUPPLIER, 'name' => 'Supplier', 'slug' => 'supplier'],
            ['id' => self::TYPE_WAREHOUSE, 'name' => 'Warehouse', 'slug' => 'warehouse'],
            ['id' => self::TYPE_V_WAREHOUSE, 'name' => 'V.Warehouse', 'slug' => 'vwarehouse'],
            ['id' => self::TYPE_BANK, 'name' => 'Bank (Account)', 'slug' => 'bank'],
            ['id' => self::TYPE_V_ACCOUNT, 'name' => 'V.Account', 'slug' => 'vaccount'],
            ['id' => self::TYPE_ACCOUNT, 'name' => 'Account', 'slug' => 'account'],
        ];
    }

    protected $casts = [
        'type' => 'integer',
        'ppn' => 'boolean',
    ];

    public $appends = ['type_name', 'type_slug'];

    public function scopeCustomer(Builder $query)
    {
        return $query->where('type', self::TYPE_CUSTOMER);
    }

    public function scopeWarehouse(Builder $query)
    {
        return $query->where('type', self::TYPE_WAREHOUSE);
    }

    public function scopeAccount(Builder $query)
    {
        return $query->where('type', self::TYPE_ACCOUNT);
    }

    public function getTypeNameAttribute(): string
    {
        $types = collect(self::getTypes());
        $type = $types->firstWhere('id', $this->type);

        return $type ? $type['name'] : 'Other';
    }

    public function getTypeSlugAttribute(): string
    {
        $types = collect(self::getTypes());
        $type = $types->firstWhere('id', $this->type);

        return $type ? $type['slug'] : 'other';
    }

    public function stat()
    {
        return $this->hasOne(AddrbookStat::class, 'addrbook_id');
    }

    public function dailies()
    {
        return $this->hasMany(AddrbookDaily::class, 'addrbook_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'warehouse_items', 'warehouse_id', 'item_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }
}
