<?php

namespace App\Models;

use App\Enums\ItemBrand;
use App\Support\FillsProductionColumnDefaults;
use App\Support\ItemImageResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemGroup extends Model
{
    use FillsProductionColumnDefaults, HasFactory;

    protected $table = 'item_group';

    public $timestamps = false;

    protected $appends = ['image_url', 'in_warehouse_qty'];

    protected $fillable = [
        'name',
        'description',
        'description2',
        'url',
        'master',
        'variant',
        'brand',
        'genre',
    ];

    protected function casts(): array
    {
        return [
            'brand' => ItemBrand::class,
            'genre' => 'integer',
        ];
    }

    public static function getPermissions(): array
    {
        return [
            'view' => 'stuff-group-list',
            'create' => 'stuff-group-create',
            'edit' => 'stuff-group-edit',
            'delete' => 'stuff-group-delete',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'group_id');
    }

    public function getImageUrlAttribute(): string
    {
        return app(ItemImageResolver::class)->resolveUrlForGroup($this);
    }

    public function getInWarehouseQtyAttribute(): float
    {
        return $this->items()->join('warehouse_item', 'items.id', '=', 'warehouse_item.item_id')->sum('warehouse_item.quantity');
    }
}
