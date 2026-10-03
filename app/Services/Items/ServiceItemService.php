<?php

namespace App\Services\Items;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Support\ItemCatalog;
use App\Support\ItemInventorySettings;
use Exception;
use Illuminate\Support\Facades\DB;

class ServiceItemService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            $code = $this->normalizeCode((string) ($data['code'] ?? ''));
            $name = strtoupper(trim((string) ($data['name'] ?? '')));
            if ($name === '') {
                throw new Exception('Name is required.');
            }

            $this->assertUniqueCode($code);

            $group = ItemGroup::query()->create([
                'master' => $code,
                'variant' => '',
                'name' => $name,
                'description' => strtoupper(trim((string) ($data['description'] ?? ''))),
                'description2' => strtoupper(trim((string) ($data['description2'] ?? ''))),
                'brand' => ItemBrand::NO_BRAND,
                'genre' => 0,
            ]);

            $item = new Item;
            $item->type = ItemType::SERVICE;
            $item->group_id = $group->id;
            $item->code = $code;
            $item->pcode = $code;
            $item->name = $name;
            $item->price = (float) ($data['price'] ?? 0);
            $item->cost = (float) ($data['cost'] ?? 0);
            $item->cost_cnh = 0;
            $item->qty = 0;
            $item->tag_ids = '';
            $item->track_inventory = ItemInventorySettings::resolveTrackInventory(ItemType::SERVICE, $data);
            $item->allow_decimal_quantity = ItemInventorySettings::resolveAllowDecimalQuantity(ItemType::SERVICE, $data);
            ItemCatalog::mirrorToItem($item, [
                'description' => (string) $item->description,
                'description2' => (string) $item->description2,
            ]);
            $item->save();

            return $item->fresh(['group']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Item $item, array $data): Item
    {
        if ($item->type !== ItemType::SERVICE) {
            throw new Exception('Not a service item.');
        }

        return DB::transaction(function () use ($item, $data) {
            $code = $this->normalizeCode((string) ($data['code'] ?? $item->code));
            $name = strtoupper(trim((string) ($data['name'] ?? $item->name)));
            if ($name === '') {
                throw new Exception('Name is required.');
            }

            if (strtoupper($item->code) !== $code) {
                $this->assertUniqueCode($code, $item->id);
            }

            $item->code = $code;
            $item->pcode = $code;
            $item->name = $name;
            $item->price = (float) ($data['price'] ?? $item->price);
            $item->cost = (float) ($data['cost'] ?? $item->cost);
            $item->track_inventory = ItemInventorySettings::resolveTrackInventory(ItemType::SERVICE, $data, $item);
            $item->allow_decimal_quantity = ItemInventorySettings::resolveAllowDecimalQuantity(ItemType::SERVICE, $data, $item);

            $description = strtoupper(trim((string) ($data['description'] ?? $item->description)));
            $description2 = strtoupper(trim((string) ($data['description2'] ?? $item->description2)));
            ItemCatalog::mirrorToItem($item, [
                'description' => $description,
                'description2' => $description2,
            ]);

            $item->save();

            if ($item->group_id) {
                ItemGroup::query()->whereKey($item->group_id)->update([
                    'name' => $name,
                    'master' => $code,
                    'description' => $description,
                    'description2' => $description2,
                ]);
            }

            return $item->fresh(['group']);
        });
    }

    protected function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 50) {
            throw new Exception('SKU code is required (max 50 characters).');
        }

        if (! preg_match('/^[A-Z0-9][A-Z0-9._\-\/]*$/', $code)) {
            throw new Exception('SKU code may only contain letters, numbers, dash, dot, slash, underscore.');
        }

        return $code;
    }

    protected function assertUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = Item::query()->whereSku($code);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw new Exception("SKU already exists: {$code}");
        }
    }
}
