<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHealthSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sold_period' => 'decimal:2',
            'returned_period' => 'decimal:2',
            'sold_extended' => 'decimal:2',
            'returned_extended' => 'decimal:2',
            'current_stock' => 'decimal:2',
            'last_sold_at' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'extended_from' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }
}
