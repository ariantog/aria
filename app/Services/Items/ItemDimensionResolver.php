<?php

namespace App\Services\Items;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemDimensionResolver
{
    public function __construct(private readonly ItemIdentityBuilder $identityBuilder) {}

    public function findItem(int $itemId): ?Item
    {
        $row = DB::table('items')
            ->where('id', $itemId)
            ->whereNull('deleted_at')
            ->first();

        if (! $row) {
            return null;
        }

        $item = new Item;
        $item->mergeCasts(['type' => 'integer', 'brand' => 'integer']);
        $item->setRawAttributes((array) $row, true);
        $item->load(['tags', 'group']);

        return $item;
    }

    /**
     * @return array{
     *     item_type: int,
     *     group_id: ?int,
     *     pcode: string,
     *     type_code: string,
     *     warna_code: string,
     *     size_code: string,
     *     brand: ?int
     * }
     */
    public function resolve(Item $item): array
    {
        $item->loadMissing(['tags', 'group']);

        $itemType = $item->type instanceof ItemType ? $item->type : ItemType::tryFrom((int) $item->type);
        $typeValue = $itemType?->value ?? ItemType::ITEM->value;

        if ($itemType === ItemType::ASSET_LANCAR) {
            $typeTag = $item->tags->firstWhere('type', Tag::TYPE_TYPE);
            $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
            $sizeCode = $this->identityBuilder->assetLancarSizeCode($item) ?? '-';

            return [
                'item_type' => $typeValue,
                'group_id' => $this->normalizeGroupId($item->group_id),
                'pcode' => $this->identityBuilder->assetLancarParentPcode($item),
                'type_code' => $typeTag ? strtoupper($typeTag->code) : '-',
                'warna_code' => $warnaTag ? strtoupper($warnaTag->code) : $this->identityBuilder->assetLancarColorLabel($item),
                'size_code' => $sizeCode !== null && $sizeCode !== '' ? strtoupper($sizeCode) : '-',
                'brand' => null,
            ];
        }

        $genreTag = $item->genre ? Tag::find($item->genre) : null;
        $sizeTag = $item->size ? Tag::find($item->size) : null;
        $brand = $item->brand instanceof ItemBrand ? $item->brand->value : (is_numeric($item->brand) ? (int) $item->brand : null);

        return [
            'item_type' => $typeValue,
            'group_id' => $this->normalizeGroupId($item->group_id),
            'pcode' => strtoupper(trim($item->pcode ?: ($item->group?->master ?? ''))) ?: '-',
            'type_code' => $genreTag ? strtoupper($genreTag->code) : '-',
            'warna_code' => '-',
            'size_code' => $sizeTag ? strtoupper($sizeTag->code) : '-',
            'brand' => $brand > 0 ? $brand : null,
        ];
    }

    private function normalizeGroupId(mixed $groupId): ?int
    {
        $id = (int) $groupId;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, array<string, mixed>>
     */
    public function resolveMany(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = DB::table('items')
            ->whereIn('id', $itemIds)
            ->whereNull('deleted_at')
            ->get();

        $items = collect();
        foreach ($rows as $row) {
            $item = new Item;
            $item->mergeCasts(['type' => 'integer', 'brand' => 'integer']);
            $item->setRawAttributes((array) $row, true);
            $item->load(['tags', 'group']);
            $items->put((int) $row->id, $item);
        }

        $resolved = [];
        foreach ($itemIds as $itemId) {
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }
            $resolved[$itemId] = $this->resolve($item);
        }

        return $resolved;
    }

    public function grainKey(string $grain, array $dims): string
    {
        return match ($grain) {
            'item' => (string) ($dims['item_id'] ?? ''),
            'pcode' => $dims['pcode'] ?? '-',
            'type' => $dims['type_code'] ?? '-',
            'type_size' => ($dims['type_code'] ?? '-').'|'.($dims['size_code'] ?? '-'),
            'type_warna' => ($dims['type_code'] ?? '-').'|'.($dims['warna_code'] ?? '-'),
            'type_warna_size' => ($dims['type_code'] ?? '-').'|'.($dims['warna_code'] ?? '-').'|'.($dims['size_code'] ?? '-'),
            'warna_size' => ($dims['warna_code'] ?? '-').'|'.($dims['size_code'] ?? '-'),
            default => '-',
        };
    }

    public function grainLabel(string $grain, array $dims, ?string $itemName = null): string
    {
        return match ($grain) {
            'item' => $itemName ?: ($dims['pcode'] ?? 'Item'),
            'pcode' => $dims['pcode'] ?? '-',
            'type' => $dims['type_code'] ?? '-',
            'type_size' => ($dims['type_code'] ?? '-').' / '.($dims['size_code'] ?? '-'),
            'type_warna' => ($dims['type_code'] ?? '-').' / '.($dims['warna_code'] ?? '-'),
            'type_warna_size' => ($dims['type_code'] ?? '-').' / '.($dims['warna_code'] ?? '-').' / '.($dims['size_code'] ?? '-'),
            'warna_size' => ($dims['warna_code'] ?? '-').' / '.($dims['size_code'] ?? '-'),
            default => '-',
        };
    }

    /**
     * @return list<string>
     */
    public static function validGrains(): array
    {
        return ['item', 'pcode', 'type', 'type_size', 'type_warna', 'type_warna_size', 'warna_size'];
    }

    /**
     * @return list<int>
     */
    public static function validPeriods(): array
    {
        return [30, 90, 180, 365];
    }

    public static function periodStartKey(int $periodDays): int
    {
        return (int) now()->subDays($periodDays)->format('Y') * 12 + (int) now()->subDays($periodDays)->format('n');
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public static function sumWindow(Collection $rows, int $periodDays): array
    {
        $startKey = self::periodStartKey($periodDays);
        $netQty = 0.0;
        $netValue = 0.0;

        foreach ($rows as $row) {
            $period = (int) $row->year * 12 + (int) $row->month;
            if ($period < $startKey) {
                continue;
            }

            $netQty += max(0.0, (float) $row->sold_qty - (float) $row->returned_qty);
            $netValue += max(0.0, (float) $row->sold_value - (float) $row->returned_value);
        }

        return ['net_qty' => $netQty, 'net_value' => $netValue];
    }
}
