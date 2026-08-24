<?php

namespace App\Models;

use App\Enums\ItemStockSourceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStockNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_stock' => 'decimal:2',
            'source_status' => ItemStockSourceStatus::class,
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'stock-notification-list',
            'dismiss' => 'stock-notification-dismiss',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function soldOutWarehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'sold_out_warehouse_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'source_warehouse_id');
    }

    public function triggerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'trigger_transaction_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->active()->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->dismissed_at === null && $this->read_at === null;
    }

    public function summary(): string
    {
        $itemCode = $this->item?->code ?? 'SKU';
        $soldOut = $this->soldOutWarehouse?->name ?? 'shop';
        $source = $this->sourceWarehouse?->name ?? 'warehouse';
        $qty = number_format((float) $this->source_stock, 0, ',', '.');
        $status = $this->source_status?->label() ?? 'stock';

        return "{$itemCode} sold out at {$soldOut}, {$status} at {$source} ({$qty} pcs)";
    }
}
