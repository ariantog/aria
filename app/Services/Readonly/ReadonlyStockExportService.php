<?php

namespace App\Services\Readonly;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReadonlyStockExportService
{
    /**
     * @return Collection<int, array{code: string, warehouse_id: int, warehouse_name: string, warehouse_type: string, qty: float}>
     */
    public function rows(Request $request): Collection
    {
        $query = WarehouseItem::query()
            ->join('items', 'items.id', '=', 'warehouse_item.item_id')
            ->join('customers as warehouse', 'warehouse.id', '=', 'warehouse_item.warehouse_id')
            ->whereNull('items.deleted_at')
            ->select([
                'items.code as item_code',
                'warehouse_item.warehouse_id',
                'warehouse_item.quantity',
                'warehouse.name as warehouse_name',
                'warehouse.type as warehouse_type',
            ]);

        if ($request->filled('code')) {
            $code = trim((string) $request->query('code'));
            $query->where(function (Builder $q) use ($code): void {
                $q->where('items.code', $code)
                    ->orWhere('items.legacy_code', $code)
                    ->orWhere('items.id', ctype_digit($code) ? (int) $code : -1);
            });
        }

        if ($request->filled('warehouse')) {
            $term = trim((string) $request->query('warehouse'));
            $query->where(function (Builder $q) use ($term): void {
                if (ctype_digit($term)) {
                    $q->where('warehouse_item.warehouse_id', (int) $term);
                } else {
                    $q->where('warehouse.name', 'like', '%'.$term.'%');
                }
            });
        }

        return $query
            ->orderBy('items.code')
            ->orderBy('warehouse_item.warehouse_id')
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->item_code,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => (string) $row->warehouse_name,
                'warehouse_type' => $this->warehouseTypeLabel((int) $row->warehouse_type),
                'qty' => (float) $row->quantity,
            ]);
    }

    private function warehouseTypeLabel(int $type): string
    {
        $enum = AddrbookType::coerce($type);

        return match ($enum) {
            AddrbookType::Warehouse => 'physical',
            AddrbookType::VirtualWarehouse => 'virtual',
            AddrbookType::Customer => 'channel',
            AddrbookType::Supplier => 'supplier',
            default => Addrbook::typeLabel($type),
        };
    }
}
