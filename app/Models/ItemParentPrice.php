<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemParentPrice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'parent_key',
        'product_name',
        'price',
        'reseller_price',
        'cost',
        'cost_cnh',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'reseller_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'cost_cnh' => 'decimal:2',
        ];
    }
}
