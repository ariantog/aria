<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemGroup extends Model
{
    use HasFactory, FillsProductionColumnDefaults;

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
    ];

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
        $folder = str_pad(substr((string) $this->id, -2), 2, '0', STR_PAD_LEFT);
        $filename = $this->id.'.jpg';
        $path = config('core-nation.item_image_path').$folder.'/'.$filename;

        if (file_exists($path)) {
            return config('core-nation.item_image_url').$folder.'/'.$filename;
        }

        return asset('images/default-item.svg');
    }

    public function getInWarehouseQtyAttribute(): float
    {
        return $this->items()->join('warehouse_item', 'items.id', '=', 'warehouse_item.item_id')->sum('warehouse_item.quantity');
    }
}
