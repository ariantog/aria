<?php

namespace App\Services\Restock;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Setting;
use InvalidArgumentException;

class RestockSettingsService
{
    public function defaultSupplierId(): ?int
    {
        $value = Setting::getValue('restock.default_supplier_id');

        return $value ? (int) $value : null;
    }

    public function defaultReceiverId(): ?int
    {
        $value = Setting::getValue('restock.default_receiver_id');

        return $value ? (int) $value : null;
    }

    /**
     * @return list<int>
     */
    public function stockDisplayWarehouseIds(): array
    {
        $value = Setting::getValue('restock.default_warehouse_ids', []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value)));
    }

    /**
     * @return array{
     *   default_supplier_id: ?int,
     *   default_receiver_id: ?int,
     *   default_warehouse_ids: list<int>,
     *   supplier: ?Addrbook,
     *   receiver: ?Addrbook,
     *   warehouses: \Illuminate\Support\Collection<int, Addrbook>
     * }
     */
    public function formData(): array
    {
        $supplierId = $this->defaultSupplierId();
        $receiverId = $this->defaultReceiverId();
        $warehouseIds = $this->stockDisplayWarehouseIds();

        return [
            'default_supplier_id' => $supplierId,
            'default_receiver_id' => $receiverId,
            'default_warehouse_ids' => $warehouseIds,
            'supplier' => $supplierId ? Addrbook::find($supplierId) : null,
            'receiver' => $receiverId ? Addrbook::find($receiverId) : null,
            'warehouses' => Addrbook::query()
                ->where('type', AddrbookType::Warehouse->value)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /**
     * @param  array{default_supplier_id: int, default_receiver_id: int, default_warehouse_ids?: list<int>}  $data
     */
    public function update(array $data): void
    {
        $supplier = Addrbook::find($data['default_supplier_id']);
        $receiver = Addrbook::find($data['default_receiver_id']);

        if (! $supplier || $this->addrbookTypeValue($supplier) !== AddrbookType::Supplier->value) {
            throw new InvalidArgumentException('Default supplier must be a valid supplier contact.');
        }

        if (! $receiver || $this->addrbookTypeValue($receiver) !== AddrbookType::Warehouse->value) {
            throw new InvalidArgumentException('Default receiver must be a valid warehouse.');
        }

        $warehouseIds = collect($data['default_warehouse_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($warehouseIds !== []) {
            $validCount = Addrbook::query()
                ->where('type', AddrbookType::Warehouse->value)
                ->whereIn('id', $warehouseIds)
                ->count();

            if ($validCount !== count($warehouseIds)) {
                throw new InvalidArgumentException('One or more stock display warehouses are invalid.');
            }
        }

        $this->persistSetting('restock.default_supplier_id', 'Default Supplier', $supplier->id);
        $this->persistSetting('restock.default_receiver_id', 'Default Receiver (Warehouse)', $receiver->id);
        $this->persistSetting('restock.default_warehouse_ids', 'Stock Display Warehouses', $warehouseIds);
    }

    public function stockQuantityForItem(?\App\Models\Item $item): int
    {
        if (! $item) {
            return 0;
        }

        $warehouseItems = $item->relationLoaded('warehouseItems')
            ? $item->warehouseItems
            : $item->warehouseItems()->get();

        $warehouseIds = $this->stockDisplayWarehouseIds();

        if ($warehouseIds !== []) {
            $warehouseItems = $warehouseItems->whereIn('warehouse_id', $warehouseIds);
        }

        return (int) $warehouseItems->sum('quantity');
    }

    protected function persistSetting(string $slug, string $name, mixed $value): void
    {
        Setting::updateOrCreate(
            ['slug' => $slug],
            ['group' => 'Restock', 'name' => $name, 'value' => $value],
        );
    }

    /**
     * @return array{supplier: Addrbook, receiver: Addrbook}
     */
    public function resolveReceiveParties(): array
    {
        $supplierId = $this->defaultSupplierId();
        $receiverId = $this->defaultReceiverId();

        if (! $supplierId || ! $receiverId) {
            throw new InvalidArgumentException('Configure restock default supplier and receiver warehouse in restock settings.');
        }

        $supplier = Addrbook::find($supplierId);
        $receiver = Addrbook::find($receiverId);

        if (! $supplier) {
            throw new InvalidArgumentException('Restock default supplier not found.');
        }

        if (! $receiver) {
            throw new InvalidArgumentException('Restock default receiver warehouse not found.');
        }

        if ($this->addrbookTypeValue($supplier) !== AddrbookType::Supplier->value) {
            throw new InvalidArgumentException('Restock default supplier must be a supplier contact.');
        }

        if ($this->addrbookTypeValue($receiver) !== AddrbookType::Warehouse->value) {
            throw new InvalidArgumentException('Restock default receiver must be a warehouse.');
        }

        return [
            'supplier' => $supplier,
            'receiver' => $receiver,
        ];
    }

    protected function addrbookTypeValue(Addrbook $addrbook): int
    {
        return $addrbook->type instanceof AddrbookType
            ? $addrbook->type->value
            : (int) $addrbook->type;
    }
}
