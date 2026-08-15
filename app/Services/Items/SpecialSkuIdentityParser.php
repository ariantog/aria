<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SpecialSkuIdentityParser
{
    /** @var Collection<int, Tag> */
    private Collection $sizeTags;

    /** @var Collection<string, Tag> */
    private Collection $warnaTagsByCode;

    public function __construct(
        private readonly SpecialSkuConverterRules $rules,
        private readonly ItemIdentityBuilder $identityBuilder,
        ?Collection $sizeTags = null,
        ?Collection $warnaTags = null,
    ) {
        $sizeTags ??= Tag::query()->where('type', Tag::TYPE_SIZE)->get();
        $warnaTags ??= Tag::query()->where('type', Tag::TYPE_WARNA)->get();

        $this->sizeTags = $sizeTags
            ->sortByDesc(fn (Tag $tag) => strlen((string) $tag->code))
            ->values();
        $this->warnaTagsByCode = $warnaTags->keyBy(fn (Tag $tag) => strtoupper($tag->code));
    }

    public function parse(Item $item): LegacyParseResult
    {
        $originalCode = strtoupper(trim((string) $item->code));

        if ($originalCode === '') {
            return LegacyParseResult::failure(
                SpecialSkuConverterRules::FAILURE_INVALID_STRUCTURE,
                'Item code is empty.',
                ['code' => $item->code],
            );
        }

        $itemType = $this->resolveItemType($item);

        if ($itemType !== ItemType::ASSET_LANCAR) {
            return LegacyParseResult::failure(
                SpecialSkuConverterRules::FAILURE_NOT_SPECIAL,
                'Special converter only supports asset lancar items.',
                ['type' => $itemType?->value ?? $item->getAttributes()['type'] ?? null],
            );
        }

        $parsed = $this->rules->parseLegacyCode($originalCode);

        if ($parsed === null) {
            return LegacyParseResult::failure(
                SpecialSkuConverterRules::FAILURE_NOT_SPECIAL,
                'Code does not match any special legacy SKU pattern.',
                ['code' => $originalCode],
            );
        }

        try {
            $this->identityBuilder->validatePcode(ItemType::ASSET_LANCAR, $parsed['pcode']);
        } catch (InvalidArgumentException $e) {
            return LegacyParseResult::failure(
                SpecialSkuConverterRules::FAILURE_INVALID_STRUCTURE,
                $e->getMessage(),
                ['code' => $originalCode, 'pcode' => $parsed['pcode']],
            );
        }

        try {
            $warnaTag = $this->resolveWarnaTag($parsed['color']);
            $sizeTag = $this->resolveSizeTag($parsed['size']);
        } catch (InvalidArgumentException $e) {
            return LegacyParseResult::failure(
                SpecialSkuConverterRules::FAILURE_INVALID_STRUCTURE,
                $e->getMessage(),
                ['code' => $originalCode, 'pcode' => $parsed['pcode']],
            );
        }

        $canonicalCode = $this->identityBuilder->buildCode(
            ItemType::ASSET_LANCAR,
            $parsed['pcode'],
            null,
            $warnaTag,
            $sizeTag,
        );

        $groupName = $this->deriveGroupName($item, $parsed['pcode']);

        return LegacyParseResult::success(
            pcode: $parsed['pcode'],
            typeCode: null,
            warnaCode: strtoupper($warnaTag->code),
            sizeCode: strtoupper((string) $sizeTag->code),
            canonicalCode: $canonicalCode,
            groupName: $groupName,
            legacyCode: $originalCode,
            codeUnchanged: $canonicalCode === $originalCode,
            snapshot: [
                'converter' => 'special',
                'family_id' => $parsed['family_id'],
                'original_code' => $originalCode,
            ],
        );
    }

    protected function resolveItemType(Item $item): ?ItemType
    {
        $raw = $item->getAttributes()['type'] ?? null;

        if ($raw instanceof ItemType) {
            return $raw;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        return ItemType::tryFrom((int) $raw);
    }

    protected function resolveWarnaTag(string $warnaCode): Tag
    {
        $warnaCode = strtoupper(trim($warnaCode));

        if ($this->warnaTagsByCode->has($warnaCode)) {
            return $this->warnaTagsByCode->get($warnaCode);
        }

        $tag = Tag::findWarnaTag($warnaCode) ?? Tag::findOrCreateWarnaTag($warnaCode);

        $this->warnaTagsByCode->put(strtoupper($tag->code), $tag);

        return $tag;
    }

    protected function resolveSizeTag(string $sizeCode): Tag
    {
        $sizeCode = strtoupper(trim($sizeCode));

        $existing = $this->sizeTags->first(
            fn (Tag $tag) => strtoupper((string) $tag->code) === $sizeCode
                || strtoupper((string) $tag->name) === $sizeCode
        ) ?? Tag::findSizeTag($sizeCode);

        if ($existing) {
            return $existing;
        }

        return Tag::findOrCreateSizeTag($sizeCode);
    }

    protected function deriveGroupName(Item $item, string $pcode): string
    {
        $name = trim((string) $item->name);

        if ($name !== '' && str_contains($name, ' - ')) {
            return strtoupper(trim(explode(' - ', $name, 2)[0]));
        }

        if ($name !== '') {
            return strtoupper($name);
        }

        return strtoupper(str_replace('-', ' ', explode('-', $pcode, 2)[0] ?? $pcode));
    }
}
