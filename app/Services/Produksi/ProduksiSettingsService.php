<?php

namespace App\Services\Produksi;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Setting;

class ProduksiSettingsService
{
    public function defaultWarehouseId(): ?int
    {
        $value = Setting::getValue('produksi.default_warehouse_id');

        return $value ? (int) $value : null;
    }

    public function resolveWarehouse(): ?Addrbook
    {
        $warehouseId = $this->defaultWarehouseId();
        if ($warehouseId) {
            $warehouse = Addrbook::find($warehouseId);
            if ($warehouse && $this->isWarehouse($warehouse)) {
                return $warehouse;
            }
        }

        return Addrbook::where('type', AddrbookType::Warehouse->value)->first();
    }

    protected function isWarehouse(Addrbook $addrbook): bool
    {
        $type = $addrbook->type;

        return ($type instanceof AddrbookType ? $type->value : (int) $type) === AddrbookType::Warehouse->value;
    }
}
