<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use InvalidArgumentException;
use RuntimeException;

class ItemIdentityBuilder
{
    public const ALL_SIZE_CODE = 'AS';

    /**
     * Production `item_group.name` is varchar(50) UNIQUE (see database/old.sql).
     */
    public const GROUP_NAME_MAX_LENGTH = 50;

    /**
     * Manufactured item pcode: [2-3 letters][5 digits]-[2-3 digits] e.g. CX90233-23
     */
    private const ITEM_PCODE_PATTERN = '/^[A-Z]{2,3}[0-9]{5}-[0-9]{2,3}$/i';

    /**
     * Asset lancar pcode: [characters]-[characters] or [characters]-[characters]-[characters]
     * e.g. GLOVE-01, BAG-16-03
     */
    private const ASSET_PCODE_PATTERN = '/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+){1,2}$/i';

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
              ? 'Expected format like GLOVE-01 or BAG-16-03'
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

    /**
     * Stored item_group.name must be globally unique in production (UNIQUE index on name).
     * Asset lancar groups are keyed by color variant, so append the warna code to the product name.
     */
    public function storedGroupName(
        ItemType $type,
        string $productName,
        string $pcode,
        string $groupVariant,
    ): string {
        $productName = strtoupper(trim($productName));
        $pcode = strtoupper(trim($pcode));
        $groupVariant = strtoupper(trim($groupVariant));

        if ($productName === '') {
            $productName = $pcode;
        }

        if ($type === ItemType::ASSET_LANCAR && $groupVariant !== '') {
            return "{$productName} - {$groupVariant}";
        }

        return $productName;
    }

    /**
     * Trim a stored group name to the production column width.
     */
    public function fitStoredGroupName(string $name): string
    {
        $name = strtoupper(trim($name));

        if ($name === '' || mb_strlen($name) <= self::GROUP_NAME_MAX_LENGTH) {
            return $name;
        }

        return rtrim(mb_substr($name, 0, self::GROUP_NAME_MAX_LENGTH));
    }

    /**
     * Return a globally unique item_group.name that fits varchar(50).
     *
     * When another master/variant already owns the preferred name, append the
     * shortest disambiguator that still fits (master, then master/variant,
     * then a numeric suffix).
     */
    public function uniqueStoredGroupName(string $storedName, string $master, string $variant): string
    {
        $storedName = $this->fitStoredGroupName($storedName);
        $master = strtoupper(trim($master));
        $variant = strtoupper(trim($variant));

        if (! $this->storedGroupNameConflicts($storedName, $master, $variant)) {
            return $storedName;
        }

        $suffixes = [];

        if ($master !== '') {
            $suffixes[] = ' ('.$master.')';
        }

        if ($master !== '' && $variant !== '') {
            $suffixes[] = ' ('.$master.'/'.$variant.')';
        }

        foreach ($suffixes as $suffix) {
            $candidate = $this->joinStoredGroupName($storedName, $suffix);

            if (! $this->storedGroupNameConflicts($candidate, $master, $variant)) {
                return $candidate;
            }
        }

        for ($n = 2; $n <= 99; $n++) {
            $candidate = $this->joinStoredGroupName($storedName, ' ('.$n.')');

            if (! $this->storedGroupNameConflicts($candidate, $master, $variant)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Unable to allocate a unique item_group.name within '.self::GROUP_NAME_MAX_LENGTH.' characters.',
        );
    }

    /**
     * Reverse storedGroupName for item display names (buildName expects the bare product title).
     */
    public function productDisplayName(ItemType $type, string $storedGroupName, string $groupVariant): string
    {
        $storedGroupName = strtoupper(trim($storedGroupName));
        $groupVariant = strtoupper(trim($groupVariant));

        if ($type === ItemType::ASSET_LANCAR && $groupVariant !== '') {
            $suffix = ' - '.$groupVariant;

            if (str_ends_with($storedGroupName, $suffix)) {
                return substr($storedGroupName, 0, -strlen($suffix));
            }
        }

        return $storedGroupName;
    }

    /**
     * Split a legacy asset lancar SKU into pcode and warna/size remainder.
     *
     * @return array{pcode: string, remainder: string}
     */
    public function splitAssetSku(string $code): array
    {
        $code = strtoupper(trim($code));
        $segments = explode('-', $code);

        if (count($segments) < 3) {
            throw new InvalidArgumentException('Asset code requires at least three hyphen segments.');
        }

        if (count($segments) >= 4 && ctype_digit($segments[2])) {
            $pcode = implode('-', array_slice($segments, 0, 3));

            try {
                $this->validatePcode(ItemType::ASSET_LANCAR, $pcode);
                $remainder = implode('-', array_slice($segments, 3));

                if ($remainder !== '') {
                    return [
                        'pcode' => $pcode,
                        'remainder' => $remainder,
                    ];
                }
            } catch (InvalidArgumentException) {
                // Fall through to two-segment pcode.
            }
        }

        $pcode = $segments[0].'-'.$segments[1];
        $this->validatePcode(ItemType::ASSET_LANCAR, $pcode);
        $remainder = implode('-', array_slice($segments, 2));

        if ($remainder === '') {
            throw new InvalidArgumentException('Missing warna segment after pcode.');
        }

        return [
            'pcode' => $pcode,
            'remainder' => $remainder,
        ];
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

        if (count($parts) >= 4 && ctype_digit($parts[2])) {
            $candidate = implode('-', array_slice($parts, 0, 3));

            if (preg_match(self::ASSET_PCODE_PATTERN, $candidate)) {
                return $candidate;
            }
        }

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
        $pcodeSegmentCount = count(explode('-', $this->assetLancarParentPcode($item)));

        if (count($parts) > $pcodeSegmentCount) {
            return $parts[$pcodeSegmentCount];
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
        $pcodeSegmentCount = count(explode('-', $this->assetLancarParentPcode($item)));
        $sizeIndex = $pcodeSegmentCount + 1;

        if (count($parts) > $sizeIndex) {
            return $parts[$sizeIndex];
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
        if ($this->resolveItemType($item) === ItemType::ASSET_LANCAR) {
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
        if ($this->resolveItemType($item) === ItemType::ASSET_LANCAR) {
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

    private function resolveItemType(Item $item): ItemType
    {
        $raw = $item->getAttributes()['type'] ?? null;

        if ($raw instanceof ItemType) {
            return $raw;
        }

        return ItemType::tryFrom((int) $raw) ?? ItemType::ITEM;
    }

    private function joinStoredGroupName(string $name, string $suffix): string
    {
        $suffix = strtoupper($suffix);
        $suffixLength = mb_strlen($suffix);

        if ($suffixLength >= self::GROUP_NAME_MAX_LENGTH) {
            $suffix = ' ('.mb_substr(md5($suffix), 0, 6).')';
            $suffixLength = mb_strlen($suffix);
        }

        $base = rtrim(mb_substr($name, 0, self::GROUP_NAME_MAX_LENGTH - $suffixLength));

        return $this->fitStoredGroupName($base.$suffix);
    }

    private function storedGroupNameConflicts(string $name, string $master, string $variant): bool
    {
        $existing = ItemGroup::query()->where('name', $name)->first();

        if (! $existing) {
            return false;
        }

        return strtoupper((string) $existing->master) !== $master
            || strtoupper((string) $existing->variant) !== $variant;
    }
}
