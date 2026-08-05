<?php

namespace App\Services\Items;

use App\Enums\ItemType;
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
     * Display name: {group.name} - {warna name} - {size name}
     * e.g. SLASH RUNNING SHIRT - BLUE - S
     */
    public function buildName(string $groupName, ?Tag $warnaTag, ?Tag $sizeTag): string
    {
        $parts = [strtoupper(trim($groupName))];

        if ($warnaTag) {
            $parts[] = strtoupper($warnaTag->name);
        }

        if ($sizeTag && ! $this->isAllSize($sizeTag)) {
            $parts[] = strtoupper($sizeTag->code);
        }

        return implode(' - ', array_filter($parts, fn ($p) => $p !== ''));
    }

    public function isAllSize(?Tag $sizeTag): bool
    {
        return $sizeTag && strtoupper($sizeTag->code) === self::ALL_SIZE_CODE;
    }
}
