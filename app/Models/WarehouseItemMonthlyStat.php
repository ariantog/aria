<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItemMonthlyStat extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sold_qty' => 'decimal:2',
            'returned_qty' => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
