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
     * @return array{supplier: Addrbook, receiver: Addrbook}
     */
    public function resolveReceiveParties(): array
    {
        $supplierId = $this->defaultSupplierId();
        $receiverId = $this->defaultReceiverId();

        if (! $supplierId || ! $receiverId) {
            throw new InvalidArgumentException('Configure restock default supplier and receiver warehouse in system settings.');
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
