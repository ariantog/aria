<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use InvalidArgumentException;

class ItemIdentityBuilder
{
    public const ALL_SIZE_CODE = 'AS';

    /**
     * Manufactured item pcode: [2-3 letters][5 digits]-[2-3 digits] e.g. CX90233-23
     */
    private const ITEM_PCODE_PATTERN = '/^[A-Z]{2,3}[0-9]{5}-[0-9]{2,3}$/i';

    /**
     * Asset lancar pcode: [characters]-[characters] e.g. GLOVE-01
     */
    private const ASSET_PCODE_PATTERN = '/^[A-Za-z0-9]+-[A-Za-z0-9]+$/';

    public function validatePcode(ItemType $type, string $pcode): void
    {
        $pcode = strtoupper(trim($pcode));

        if ($pcode === '') {
            throw new InvalidArgumentException('pcode is required');
        }

        $pattern = $type === ItemType::ASSET_LANCAR
          ? self::ASSET_PCODE_PATTERN
          : self::ITEM_PCODE_PATTERN;

        if (! preg_match($pattern, $pcode)) {
            $hint = $type === ItemType::ASSET_LANCAR
              ? 'Expected format like GLOVE-01'
              : 'Expected format like CX90233-23';

            throw new InvalidArgumentException("pcode format invalid. {$hint}");
        }
    }

    /**
     * @return array{master: string, variant: ?string}
     */
    public function parsePcode(ItemType $type, string $pcode): array
    {
        $this->validatePcode($type, $pcode);
        $pcode = strtoupper(trim($pcode));

        if ($type === ItemType::ASSET_LANCAR) {
            return [
                'master' => $pcode,
                'variant' => null,
            ];
        }

        [$master, $variant] = explode('-', $pcode, 2);

        return [
            'master' => $master,
            'variant' => $variant,
        ];
    }

    /**
     * Grouping key variant segment (color number in pcode for items, warna code for assets).
     */
    public function groupVariant(ItemType $type, string $pcode, ?Tag $warnaTag): string
    {
        if ($type === ItemType::ITEM) {
            return $this->parsePcode($type, $pcode)['variant'] ?? '';
        }

        return strtoupper($warnaTag?->code ?? '');
    }

    /**
     * Build the canonical SKU stored in items.code.
     *
     * Item:      {typeTag}-{pcode}-{size?}           e.g. AJD-CX90324-05-S
     * Asset:     {pcode}-{warna}-{size?}              e.g. GLOVE-01-BLACK-S
     * All-size:  omit the trailing -{size} segment
     */
    public function buildCode(
        ItemType $type,
        string $pcode,
        ?Tag $typeTag,
        ?Tag $warnaTag,
        ?Tag $sizeTag,
    ): string {
        $pcode = strtoupper(trim($pcode));
        $segments = [];

        if ($type === ItemType::ITEM) {
            if (! $typeTag) {
                throw new InvalidArgumentException('TYPE tag is required for manufactured items');
            }

            $segments[] = strtoupper($typeTag->code);
            $segments[] = $pcode;
        } else {
            $segments[] = $pcode;

            if (! $warnaTag) {
                throw new InvalidArgumentException('Warna tag is required for asset lancar items');
            }

            $segments[] = strtoupper($warnaTag->code);
        }

        if ($sizeTag && ! $this->isAllSize($sizeTag)) {
            $segments[] = strtoupper($sizeTag->code);
        }

        return implode('-', $segments);
    }

    /**
     * Display name: {group.name} - {warna code} - {size code}
     * e.g. SLASH RUNNING SHIRT - BLUE - S
     */
    public function buildName(string $groupName, ?Tag $warnaTag, ?Tag $sizeTag): string
    {
        $parts = [strtoupper(trim($groupName))];

        if ($warnaTag) {
            $parts[] = strtoupper($warnaTag->code);
        }

        if ($sizeTag && ! $this->isAllSize($sizeTag)) {
            $parts[] = strtoupper($sizeTag->code);
        }

        return implode(' - ', array_filter($parts, fn ($p) => $p !== ''));
    }

    public function tagCode(?Tag $tag): string
    {
        return strtoupper($tag?->code ?? '');
    }

    public function isAllSize(?Tag $sizeTag): bool
    {
        return $sizeTag && strtoupper($sizeTag->code) === self::ALL_SIZE_CODE;
    }

    /**
     * Restock parent key (TYPE-VARIANT), e.g. LIFTINGBELT-17 from SKU LIFTINGBELT-17-GREEN-XL.
     * Uses items.pcode when it matches the asset pattern; otherwise derives from items.code.
     */
    public function assetLancarParentPcode(Item $item): string
    {
        $pcode = strtoupper(trim($item->pcode ?? ''));

        if ($pcode !== '' && preg_match(self::ASSET_PCODE_PATTERN, $pcode)) {
            return $pcode;
        }

        $parts = explode('-', strtoupper(trim($item->code ?? '')));
        if (count($parts) >= 2) {
            return $parts[0].'-'.$parts[1];
        }

        return $pcode !== '' ? $pcode : strtoupper(trim($item->code ?? 'UNKNOWN'));
    }

    public function assetLancarColorLabel(Item $item): string
    {
        $warna = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_WARNA)
            : null;

        if ($warna) {
            return strtoupper($warna->code);
        }

        $parts = explode('-', strtoupper(trim($item->code ?? '')));
        if (count($parts) >= 3) {
            return $parts[2];
        }

        return '—';
    }

    public function assetLancarColorGroupKey(Item $item): string
    {
        $warna = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_WARNA)
            : null;

        if ($warna) {
            return 'tag:'.$warna->id;
        }

        $label = $this->assetLancarColorLabel($item);
        if ($label !== '—') {
            return 'code:'.$label;
        }

        return 'none';
    }

    public function assetLancarSizeCode(Item $item): ?string
    {
        $sizeTag = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_SIZE)
            : null;

        if ($sizeTag) {
            return $this->isAllSize($sizeTag) ? null : strtoupper($sizeTag->code);
        }

        $parts = explode('-', strtoupper(trim($item->code ?? '')));
        if (count($parts) >= 4) {
            return $parts[3];
        }

        return null;
    }

    /**
     * Parent group key for list/detail hierarchy.
     * Manufactured: 1:{TYPE}:{master pcode}  e.g. 1:AJD:CX93024
     * Asset lancar:  2:{parent pcode}        e.g. 2:GLOVE-01
     */
    public function itemParentKey(Item $item): string
    {
        if ($item->type === ItemType::ASSET_LANCAR) {
            return ItemType::ASSET_LANCAR->value.':'.$this->assetLancarParentPcode($item);
        }

        $typeCode = $this->manufacturedTypeCode($item);

        return ItemType::ITEM->value.':'.$typeCode.':'.$this->manufacturedParentMaster($item);
    }

    /**
     * Parent group display label: TYPE + master pcode (manufactured) or parent pcode (asset).
     */
    public function itemParentLabel(Item $item): string
    {
        if ($item->type === ItemType::ASSET_LANCAR) {
            return $this->assetLancarParentPcode($item);
        }

        return $this->manufacturedTypeCode($item).' '.$this->manufacturedParentMaster($item);
    }

    public function parentKeyToSlug(string $parentKey): string
    {
        return str_replace(':', '__', $parentKey);
    }

    public function parentKeyFromSlug(string $slug): string
    {
        return str_replace('__', ':', $slug);
    }

    public function itemColorGroupKey(Item $item): string
    {
        if ($item->type === ItemType::ASSET_LANCAR) {
            return $this->assetLancarColorGroupKey($item);
        }

        $warna = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_WARNA)
            : null;

        if ($warna) {
            return 'tag:'.$warna->id;
        }

        if ($item->group?->variant) {
            return 'variant:'.$item->group->variant;
        }

        return 'none';
    }

    /**
     * @return array{code: string, name: string}
     */
    public function itemColorInfo(Item $item): array
    {
        $warna = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_WARNA)
            : null;

        if ($warna) {
            return [
                'code' => strtoupper($warna->code),
                'name' => $warna->name,
            ];
        }

        if ($item->type === ItemType::ITEM && $item->group?->variant) {
            return [
                'code' => strtoupper($item->group->variant),
                'name' => 'Color '.$item->group->variant,
            ];
        }

        $code = $this->assetLancarColorLabel($item);

        return [
            'code' => $code,
            'name' => $code,
        ];
    }

    public function itemSizeCode(Item $item): ?string
    {
        if ($item->type === ItemType::ASSET_LANCAR) {
            return $this->assetLancarSizeCode($item);
        }

        $sizeTag = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_SIZE)
            : null;

        if ($sizeTag) {
            return $this->isAllSize($sizeTag) ? null : strtoupper($sizeTag->code);
        }

        $parts = explode('-', strtoupper(trim($item->code ?? '')));
        if (count($parts) >= 4) {
            return $parts[3];
        }

        return null;
    }

    public function manufacturedTypeCode(Item $item): string
    {
        $typeTag = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_TYPE)
            : null;

        if ($typeTag) {
            return strtoupper($typeTag->code);
        }

        $parts = explode('-', strtoupper(trim($item->code ?? '')));

        return $parts[0] ?? 'UNK';
    }

    public function manufacturedParentMaster(Item $item): string
    {
        if ($item->group?->master) {
            return strtoupper($item->group->master);
        }

        $pcode = strtoupper(trim($item->pcode ?? ''));
        if ($pcode !== '' && preg_match(self::ITEM_PCODE_PATTERN, $pcode)) {
            return $this->parsePcode(ItemType::ITEM, $pcode)['master'];
        }

        $parts = explode('-', strtoupper(trim($item->code ?? '')));
        if (count($parts) >= 2) {
            return $parts[1];
        }

        return $pcode !== '' ? $pcode : 'UNKNOWN';
    }
}
