<?php

namespace App\Services\Marketplace;

use App\Enums\AddrbookType;
use App\Models\Addrbook;

/**
 * Shared FLEXI staging gudang (physical warehouse) from config.
 */
class ShopeeFlexiStagingWarehouse
{
    public function configuredId(): int
    {
        return max(0, (int) config('shopee_flexi.staging_warehouse_id', 0));
    }

    public function isConfiguredDestination(int $warehouseId): bool
    {
        $configured = $this->configuredId();

        return $configured > 0 && $warehouseId === $configured;
    }

    public function resolveWarehouse(): ?Addrbook
    {
        $id = $this->configuredId();
        if ($id <= 0) {
            return null;
        }

        $row = Addrbook::query()->find($id);
        if ($row === null) {
            return null;
        }

        if (! $this->isWarehouseType($row)) {
            return null;
        }

        return $row;
    }

    /**
     * @return array{
     *     configured: bool,
     *     valid: bool,
     *     warehouse_id: int,
     *     warehouse_name: ?string,
     *     warning: ?string,
     *     arrangement_enabled: bool,
     * }
     */
    public function statusForUi(): array
    {
        $id = $this->configuredId();
        if ($id <= 0) {
            return [
                'configured' => false,
                'valid' => false,
                'warehouse_id' => 0,
                'warehouse_name' => null,
                'warning' => 'Gudang staging FLEXI belum dikonfigurasi — set SHOPEE_FLEXI_STAGING_WAREHOUSE_ID di environment.',
                'arrangement_enabled' => false,
            ];
        }

        $row = Addrbook::query()->find($id);
        if ($row === null) {
            return [
                'configured' => true,
                'valid' => false,
                'warehouse_id' => $id,
                'warehouse_name' => null,
                'warning' => "Gudang staging #{$id} tidak ditemukan di addrbook.",
                'arrangement_enabled' => false,
            ];
        }

        if (! $this->isWarehouseType($row)) {
            return [
                'configured' => true,
                'valid' => false,
                'warehouse_id' => $id,
                'warehouse_name' => (string) $row->name,
                'warning' => "Addrbook #{$id} bukan tipe gudang fisik.",
                'arrangement_enabled' => false,
            ];
        }

        $arrangement = (bool) ($row->arrangement_enabled ?? false);
        $warning = null;
        if (! $arrangement) {
            $warning = 'Gudang staging tanpa arrangement_enabled — move draft tetap bisa jika ini ID staging FLEXI yang dikonfigurasi; aktifkan arrangement jika dipakai di Warehouse Arrangement.';
        }

        return [
            'configured' => true,
            'valid' => true,
            'warehouse_id' => $id,
            'warehouse_name' => (string) $row->name,
            'warning' => $warning,
            'arrangement_enabled' => $arrangement,
        ];
    }

    private function isWarehouseType(Addrbook $addrbook): bool
    {
        $type = $addrbook->typeValue();

        return $type === AddrbookType::Warehouse->value || $type === (int) Addrbook::TYPE_WAREHOUSE;
    }
}