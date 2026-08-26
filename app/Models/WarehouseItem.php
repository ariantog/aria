<?php

namespace App\Models;

use App\Enums\AddrbookType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItem extends Model
{
    use HasFactory;

    protected $table = 'warehouse_item';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'warehouse_type',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    /** @return list<int> */
    public static function warehouseAddrbookTypes(): array
    {
        return [
            AddrbookType::Warehouse->value,
            AddrbookType::VirtualWarehouse->value,
        ];
    }

    public function scopeForWarehouseAddrbooks(Builder $query, bool $withTrashed = false): Builder
    {
        $addrbooks = $withTrashed ? Addrbook::withTrashed() : Addrbook::query();

        return $query->whereIn('warehouse_id', $addrbooks
            ->whereIn('type', self::warehouseAddrbookTypes())
            ->select('id'));
    }

    public function scopeForActiveWarehouseAddrbooks(Builder $query): Builder
    {
        return $query->forWarehouseAddrbooks(withTrashed: false);
    }

    public function scopeForDeletedWarehouseAddrbooks(Builder $query): Builder
    {
        return $query->whereIn('warehouse_id', Addrbook::onlyTrashed()
            ->whereIn('type', self::warehouseAddrbookTypes())
            ->select('id'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id')->withTrashed();
    }
}
