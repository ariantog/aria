<?php

namespace App\Models;

use App\Enums\AddrbookType;
use App\Models\Concerns\MapsProductionColumns;
use App\Models\Concerns\UsesProductionTable;
use App\Support\ProductionSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addrbook extends Model
{
    use HasFactory, MapsProductionColumns, SoftDeletes, UsesProductionTable;

    /** @deprecated Use AddrbookType::Customer */
    const TYPE_CUSTOMER = 1;

    /** @deprecated Use AddrbookType::Warehouse */
    const TYPE_WAREHOUSE = 2;

    /** @deprecated Use AddrbookType::Bank */
    const TYPE_BANK = 3;

    /** @deprecated Use AddrbookType::Supplier */
    const TYPE_SUPPLIER = 4;

    /** @deprecated Use AddrbookType::VirtualWarehouse */
    const TYPE_V_WAREHOUSE = 5;

    /** @deprecated Use AddrbookType::VirtualAccount */
    const TYPE_V_ACCOUNT = 6;

    /** @deprecated Use AddrbookType::Reseller */
    const TYPE_RESELLER = 7;

    /** @deprecated Use AddrbookType::Account */
    const TYPE_ACCOUNT = 8;

    /** @deprecated Use AddrbookType::Other */
    const TYPE_OTHER = 99;

    protected $table = 'addrbooks';

    protected $guarded = ['id'];

    protected static function productionTableKey(): string
    {
        return 'addrbook';
    }

    protected static function productionColumnKey(): string
    {
        return 'addrbooks';
    }

    protected function casts(): array
    {
        return [
            'type' => AddrbookType::class,
            'ppn' => 'boolean',
            'is_online' => 'boolean',
            'arrangement_enabled' => 'boolean',
        ];
    }

    public $appends = ['type_name', 'type_slug'];

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

    public static function getTypes(): array
    {
        return array_map(fn (AddrbookType $type) => ['id' => $type->value, 'name' => $type->label(), 'slug' => $type->slug()], AddrbookType::cases());
    }

    public function scopeCustomer(Builder $query): Builder
    {
        return $query->where('type', AddrbookType::Customer->value);
    }

    public function scopeWarehouse(Builder $query): Builder
    {
        return $query->where('type', AddrbookType::Warehouse->value);
    }

    public function scopeAccount(Builder $query): Builder
    {
        return $query->where('type', AddrbookType::Account->value);
    }

    public function getTypeNameAttribute(): string
    {
        return $this->type instanceof AddrbookType ? $this->type->label() : 'Other';
    }

    public function getTypeSlugAttribute(): string
    {
        return $this->type instanceof AddrbookType ? $this->type->slug() : 'other';
    }

    public function stat()
    {
        $foreignKey = ProductionSchema::column('addrbook_stat', 'addrbook_id');

        return $this->hasOne(AddrbookStat::class, $foreignKey);
    }

    public function dailies()
    {
        $foreignKey = ProductionSchema::column('addrbook_daily', 'addrbook_id');

        return $this->hasMany(AddrbookDaily::class, $foreignKey);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            Item::class,
            ProductionSchema::table('warehouse_item'),
            'warehouse_id',
            'item_id',
        )->withPivot('quantity')->withTimestamps();
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'addrbook_location');
    }

    /**
     * Physical warehouses that can supply stock to this arrangement destination.
     */
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
