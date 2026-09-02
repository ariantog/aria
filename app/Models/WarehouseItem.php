<?php

namespace App\Models;

use App\Enums\AddrbookType;
use App\Exceptions\InsufficientWarehouseStockException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        static::saving(function (WarehouseItem $row): void {
            $row->assertPhysicalQuantityNotNegative();
        });
    }

    /**
     * Locked increment/decrement used by every stock writer.
     * Physical warehouses cannot go below zero; virtual warehouses can.
     */
    public static function applyDelta(int $warehouseId, int $itemId, float $delta, ?int $warehouseType = null): self
    {
        return DB::transaction(function () use ($warehouseId, $itemId, $delta, $warehouseType) {
            $row = static::query()
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = new static([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'quantity' => 0,
                ]);
            }

            if ($warehouseType !== null) {
                $row->warehouse_type = $warehouseType;
            }

            $row->quantity = (float) ($row->quantity ?? 0) + $delta;
            $row->save();

            return $row;
        });
    }

    /**
     * Virtual warehouses (and non-warehouse parties such as suppliers) may store a negative qty.
     * Physical warehouses may not.
     */
    public function allowsNegativeQuantity(): bool
    {
        return $this->resolvedWarehouseType() !== Addrbook::TYPE_WAREHOUSE;
    }

    public function assertPhysicalQuantityNotNegative(): void
    {
        if ($this->exists && ! $this->isDirty('quantity')) {
            return;
        }

        if (round((float) $this->quantity, 4) >= 0) {
            return;
        }

        if ($this->allowsNegativeQuantity()) {
            return;
        }

        $available = round((float) ($this->getOriginal('quantity') ?? 0), 4);
        $new = round((float) $this->quantity, 4);
        $requested = round($available - $new, 4);
        if ($requested <= 0) {
            $requested = abs($new);
        }

        $code = $this->item?->code ?: 'item #'.($this->item_id ?? '?');

        throw new InsufficientWarehouseStockException(
            $code,
            $available,
            $requested,
            $this->warehouse_id ? (int) $this->warehouse_id : null,
        );
    }

    protected function resolvedWarehouseType(): ?int
    {
        if ($this->relationLoaded('warehouse') && $this->warehouse) {
            return (int) $this->warehouse->type;
        }

        if (! $this->warehouse_id) {
            return null;
        }

        $type = Addrbook::withTrashed()->whereKey($this->warehouse_id)->value('type');

        return $type !== null ? (int) $type : null;
    }

    /** @return list<int> */
    public static function warehouseAddrbookTypes(): array
    {
        return [
            AddrbookType::Warehouse->value,
            AddrbookType::VirtualWarehouse->value,
        ];
    }

    /** Physical warehouses only — sellable availability excludes virtual stock. */
    public static function physicalWarehouseAddrbookTypes(): array
    {
        return [AddrbookType::Warehouse->value];
    }

    public static function virtualWarehouseAddrbookTypes(): array
    {
        return [AddrbookType::VirtualWarehouse->value];
    }

    public function scopeForWarehouseAddrbooks(Builder $query, bool $withTrashed = false): Builder
    {
        return $this->constrainToAddrbookTypes($query, self::warehouseAddrbookTypes(), $withTrashed);
    }

    /**
     * Non-deleted physical warehouses. Used for availability / items.qty.
     */
    public function scopeForAvailableStock(Builder $query): Builder
    {
        return $this->constrainToAddrbookTypes($query, self::physicalWarehouseAddrbookTypes(), false);
    }

    public function scopeForActiveWarehouseAddrbooks(Builder $query): Builder
    {
        return $query->forWarehouseAddrbooks(withTrashed: false);
    }

    public function scopeForVirtualWarehouseAddrbooks(Builder $query, bool $withTrashed = false): Builder
    {
        return $this->constrainToAddrbookTypes($query, self::virtualWarehouseAddrbookTypes(), $withTrashed);
    }

    public function scopeForDeletedWarehouseAddrbooks(Builder $query): Builder
    {
        return $query->whereIn('warehouse_id', Addrbook::onlyTrashed()
            ->whereIn('type', self::warehouseAddrbookTypes())
            ->select('id'));
    }

    /**
     * @param  list<int>  $types
     */
    protected function constrainToAddrbookTypes(Builder $query, array $types, bool $withTrashed): Builder
    {
        $addrbooks = $withTrashed ? Addrbook::withTrashed() : Addrbook::query();

        return $query->whereIn('warehouse_id', $addrbooks
            ->whereIn('type', $types)
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
