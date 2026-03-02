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
        if ($type) {
            $type = str_replace('-', '', $type);
            return [
                'view' => "{$type}-addrbook-list",
                'create' => "{$type}-addrbook-create",
                'edit' => "{$type}-addrbook-edit",
                'delete' => "{$type}-addrbook-delete",
            ];
        }

        $permissions = [
            'view' => 'addrbook-list',
            'create' => 'addrbook-create',
            'edit' => 'addrbook-edit',
            'delete' => 'addrbook-delete',
        ];

        foreach (self::getTypes() as $t) {
            $slug = str_replace('-', '', $t['slug']);
            $permissions["{$slug}_view"] = "{$slug}-addrbook-list";
            $permissions["{$slug}_create"] = "{$slug}-addrbook-create";
            $permissions["{$slug}_edit"] = "{$slug}-addrbook-edit";
            $permissions["{$slug}_delete"] = "{$slug}-addrbook-delete";
        }

        return $permissions;
    }

    public static function getTypes(): array
    {
        return [
            ['id' => self::TYPE_CUSTOMER, 'name' => 'customer', 'slug' => 'customer'],
            ['id' => self::TYPE_WAREHOUSE, 'name' => 'warehaouse', 'slug' => 'warehaouse'],
            ['id' => self::TYPE_BANK, 'name' => 'account', 'slug' => 'account'],
            ['id' => self::TYPE_SUPPLIER, 'name' => 'suplier', 'slug' => 'suplier'],
            ['id' => self::TYPE_V_WAREHOUSE, 'name' => 'v.warehaous', 'slug' => 'vwarehaous'],
            ['id' => self::TYPE_V_ACCOUNT, 'name' => 'v.account', 'slug' => 'vaccount'],
            ['id' => self::TYPE_RESELLER, 'name' => 'reseller', 'slug' => 'reseller'],
            ['id' => self::TYPE_ACCOUNT, 'name' => 'Account', 'slug' => 'journal-account'],
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
}
