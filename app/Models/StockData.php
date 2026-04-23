<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockData extends Model
{
    use HasFactory;

    protected $table = 'stock_data';

    protected $fillable = [
        'id_stock_report',
        'item_id',
        'item_name',
        'score',
        'performance_key',
        'performance_level',
        'gap_days',
        'current_warehouse_id',
        'current_warehouse_name',
        'current_warehouse_qty',
        'current_warehouse_last_sale',
        'current_warehouse_days_ago',
        'best_performing_warehouse_id',
        'best_performing_warehouse_name',
        'best_performing_warehouse_last_sale',
        'best_performing_warehouse_days_ago',
        'best_performing_warehouse_qty',
        'audit_reference_date',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'gap_days' => 'integer',
            'current_warehouse_qty' => 'integer',
            'current_warehouse_days_ago' => 'integer',
            'best_performing_warehouse_days_ago' => 'integer',
            'best_performing_warehouse_qty' => 'integer',
        ];
    }

    public function stokReport(): BelongsTo
    {
        return $this->belongsTo(StokReport::class, 'id_stock_report');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function currentWarehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'current_warehouse_id');
    }

    public function bestPerformingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'best_performing_warehouse_id');
    }
}
