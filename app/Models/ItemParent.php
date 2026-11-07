<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemParent extends Model
{
    public $timestamps = false;

    protected $table = 'item_parents';

    protected $fillable = [
        'parent_key',
        'product_name',
        'description',
        'description2',
        'url',
        'brand',
        'genre',
        'price',
        'reseller_price',
        'cost',
        'cost_cnh',
    ];

    protected function casts(): array
    {
        return [
            'brand' => \App\Enums\ItemBrand::class,
            'price' => 'decimal:2',
            'reseller_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'cost_cnh' => 'decimal:2',
        ];
    }

    public function colorwayGroups(): HasMany
    {
        return $this->hasMany(ItemGroup::class, 'parent_id');
    }
}
