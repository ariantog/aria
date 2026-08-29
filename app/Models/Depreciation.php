<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depreciation extends Model
{
    use FillsProductionColumnDefaults;

    protected $table = 'depreciation';

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'value' => 'integer',
            'buy_date' => 'date',
            'buy_price' => 'decimal:2',
            'expire_date' => 'date',
            'residual_value' => 'decimal:2',
            'useful_life_months' => 'integer',
            'warehouse_id' => 'integer',
            'buy_transaction_id' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }

    public function buyTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'buy_transaction_id');
    }

    public function hasBuyTransaction(): bool
    {
        return (int) $this->buy_transaction_id > 0;
    }
}
