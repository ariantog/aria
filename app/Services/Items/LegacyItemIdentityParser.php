<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class LegacyItemIdentityParser
{
    public const FAILURE_SKU_UNPARSEABLE = 'SKU_UNPARSEABLE';

    public const FAILURE_PCODE_INVALID = 'PCODE_INVALID';

    public const FAILURE_TYPE_TAG_MISSING = 'TYPE_TAG_MISSING';

    public const FAILURE_WARNA_MISSING = 'WARNA_MISSING';

    public const FAILURE_COLOR_AMBIGUOUS = 'COLOR_AMBIGUOUS';

    public const FAILURE_COLOR_NOT_FOUND = 'COLOR_NOT_FOUND';

    private const SIZE_ALIASES = [
        '0S' => 'S',
        '0M' => 'M',
        '0L' => 'L',
    ];

    /** @var array<string, string> */
    private const BAHASA_COLOR_MAP = [
        'hitam' => 'BLACK',
        'putih' => 'WHITE',
        'merah' => 'RED',
        'biru' => 'BLUE',
        'hijau' => 'GREEN',
        'kuning' => 'YELLOW',
        'abu' => 'GRAY',
        'abu-abu' => 'GRAY',
        'coklat' => 'BROWN',
        'pink' => 'PINK',
        'merah muda' => 'PINK',
        'ungu' => 'PURPLE',
        'orange' => 'ORANGE',
        'jingga' => 'ORANGE',
    ];

    /** @var Collection<int, Tag> */
    private Collection $sizeTags;

    /** @var Collection<string, Tag> */
    private Collection $warnaTagsByCode;

    /** @var Collection<int, Tag> */
    private Collection $warnaTagsByCodeLength;

    /** @var Collection<string, Tag> */
    private Collection $typeTagsByCode;

    private ?Tag $allSizeTag;

    private ItemIdentityBuilder $identityBuilder;

    public function __construct(
        ?ItemIdentityBuilder $identityBuilder = null,
        ?Collection $sizeTags = null,
        ?Collection $warnaTags = null,
        ?Collection $typeTags = null,
        ?Tag $allSizeTag = null,
    ) {
        $this->identityBuilder = $identityBuilder ?? new ItemIdentityBuilder;

        $sizeTags ??= Tag::query()->where('type', Tag::TYPE_SIZE)->get();
        $warnaTags ??= Tag::query()->where('type', Tag::TYPE_WARNA)->get();
        $typeTags ??= Tag::manufacturedTypeTags();

        $this->sizeTags = $sizeTags
            ->sortByDesc(fn (Tag $tag) => strlen((string) $tag->code))
            ->values();

        $this->warnaTagsByCode = $warnaTags->keyBy(fn (Tag $tag) => strtoupper($tag->code));
        $this->warnaTagsByCodeLength = $warnaTags
            ->sortByDesc(fn (Tag $tag) => strlen((string) $tag->code))
            ->values();
        $this->typeTagsByCode = $typeTags->keyBy(fn (Tag $tag) => strtoupper($tag->code));
        $this->allSizeTag = $allSizeTag ?? $sizeTags->first(
            fn (Tag $tag) => strtoupper($tag->code) === ItemIdentityBuilder::ALL_SIZE_CODE
        );
    }

    public function resolveItemType(Item $item): ?ItemType
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

    public function parse(Item $item): LegacyParseResult
    {
        $code = strtoupper(trim((string) $item->code));

        if ($code === '') {
            return LegacyParseResult::failure(
                self::FAILURE_SKU_UNPARSEABLE,
                'Item code is empty.',
                ['code' => $item->code],
            );
        }

        $itemType = $this->resolveItemType($item);

        return match ($itemType) {
            ItemType::ASSET_LANCAR => $this->parseAsset($item, $code),
            ItemType::ITEM => $this->parseManufactured($item, $code),
            default => LegacyParseResult::failure(
                self::FAILURE_SKU_UNPARSEABLE,
                'Unsupported item type for conversion.',
                ['type' => $itemType?->value ?? $item->getAttributes()['type'] ?? null],
            ),
        };
    }

    public function matchSizeFromSuffix(string $remainder): ?Tag
    {
        $remainder = strtoupper(trim($remainder));

        if ($remainder === '') {
            return null;
        }

        foreach ($this->sizeTags as $tag) {
            $code = strtoupper((string) $tag->code);

            if ($remainder === $code) {
                return $tag;
            }

            if (str_ends_with($remainder, '-'.$code)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Fast structural gate before batch conversion.
     * Asset lancar: PCODE-COLOR minimum (e.g. GLOVE-01-BLACK, not HANGER-01 or ECOFEET-13-SM).
     * Manufactured: TYPE-PCODE minimum (hyphenated) or glue-code regex.
     */
    public function hasMinimumIdentityStructure(string $code, ItemType $type): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        return match ($type) {
            ItemType::ASSET_LANCAR => $this->assetHasMinimumStructure($code),
            ItemType::ITEM => $this->manufacturedHasMinimumStructure($code),
            default => false,
        };
    }

    protected function assetHasMinimumStructure(string $code): bool
    {
        try {
            ['remainder' => $remainder] = $this->identityBuilder->splitAssetSku($code);
        } catch (InvalidArgumentException) {
            return false;
        }

        $sizeTag = $this->matchSizeFromSuffix($remainder);
        $warna = $this->extractWarnaFromRemainder($remainder, $sizeTag);

        return $warna !== '';
    }

    protected function manufacturedHasMinimumStructure(string $code): bool
    {
        if (! str_contains($code, '-')) {
            return (bool) preg_match('/^([A-Z]{2,3})([A-Z]{2}[0-9]{5})([0-9]{2,3})(.+)?$/', $code);
        }

        $segments = explode('-', $code);

        if (count($segments) < 3) {
            return false;
        }

        $typeCode = $segments[0];

        if (! preg_match('/^[A-Z]{2,3}$/', $typeCode) || ! $this->typeTagsByCode->has($typeCode)) {
            return false;
        }

        $tail = $segments[count($segments) - 1];
        $sizeTag = $this->matchSizeFromSuffix($tail);

        if ($sizeTag && strtoupper((string) $sizeTag->code) === strtoupper($tail)) {
            $pcode = implode('-', array_slice($segments, 1, -1));
        } else {
            $pcode = implode('-', array_slice($segments, 1));
        }

        if ($pcode === '') {
            return false;
        }

        try {
            $this->identityBuilder->validatePcode(ItemType::ITEM, $pcode);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    protected function parseAsset(Item $item, string $code): LegacyParseResult
    {
        try {
            ['pcode' => $pcode, 'remainder' => $remainder] = $this->identityBuilder->splitAssetSku($code);
        } catch (InvalidArgumentException $e) {
            return LegacyParseResult::failure(
                self::FAILURE_SKU_UNPARSEABLE,
                $e->getMessage(),
                ['code' => $code],
            );
        }

        $sizeTag = $this->matchSizeFromSuffix($remainder);
        $warnaCode = $this->extractWarnaFromRemainder($remainder, $sizeTag);

        if ($warnaCode === '') {
            return LegacyParseResult::failure(
                self::FAILURE_WARNA_MISSING,
                'Could not resolve warna from asset code.',
                ['code' => $code, 'remainder' => $remainder],
            );
        }

        $warnaTag = $this->resolveWarnaTag($warnaCode);
        $effectiveSizeTag = $sizeTag ?? $this->allSizeTag;

        $canonicalCode = $this->identityBuilder->buildCode(
            ItemType::ASSET_LANCAR,
            $pcode,
            null,
            $warnaTag,
            $effectiveSizeTag,
        );

        $groupName = $this->deriveAssetGroupName($item, $pcode);

        return LegacyParseResult::success(
            pcode: $pcode,
            typeCode: null,
            warnaCode: strtoupper($warnaTag->code),
            sizeCode: $effectiveSizeTag ? strtoupper((string) $effectiveSizeTag->code) : null,
            canonicalCode: $canonicalCode,
            groupName: $groupName,
            codeUnchanged: $canonicalCode === $code,
            snapshot: [
                'original_code' => $code,
                'remainder' => $remainder,
            ],
        );
    }

    protected function parseManufactured(Item $item, string $code): LegacyParseResult
    {
        if (str_contains($code, '-')) {
            return $this->parseManufacturedHyphenated($item, $code);
        }

        return $this->parseManufacturedGlue($item, $code);
    }

    protected function parseManufacturedHyphenated(Item $item, string $code): LegacyParseResult
    {
        $segments = explode('-', $code);

        if (count($segments) < 2) {
            return LegacyParseResult::failure(
                self::FAILURE_SKU_UNPARSEABLE,
                'Manufactured hyphenated code is too short.',
                ['code' => $code],
            );
        }

        $typeCode = $segments[0];

        if (! $this->typeTagsByCode->has($typeCode)) {
            return LegacyParseResult::failure(
                self::FAILURE_TYPE_TAG_MISSING,
                "TYPE tag not found for code {$typeCode}.",
                ['code' => $code, 'type' => $typeCode],
            );
        }

        $tail = $segments[count($segments) - 1];
        $sizeTag = $this->matchSizeFromSuffix($tail);

        if ($sizeTag && strtoupper((string) $sizeTag->code) === strtoupper($tail)) {
            $pcode = implode('-', array_slice($segments, 1, -1));
        } else {
            $pcode = implode('-', array_slice($segments, 1));
            $sizeTag = $this->allSizeTag;
        }

        return $this->finalizeManufacturedParse($item, $code, $typeCode, $pcode, $sizeTag);
    }

    protected function parseManufacturedGlue(Item $item, string $code): LegacyParseResult
    {
        if (! preg_match('/^([A-Z]{2,3})([A-Z]{2}[0-9]{5})([0-9]{2,3})(.+)?$/', $code, $matches)) {
            return LegacyParseResult::failure(
                self::FAILURE_SKU_UNPARSEABLE,
                'Glue code does not match manufactured pattern.',
                ['code' => $code],
            );
        }

        $typeCode = $matches[1];

        if (! $this->typeTagsByCode->has($typeCode)) {
            return LegacyParseResult::failure(
                self::FAILURE_TYPE_TAG_MISSING,
                "TYPE tag not found for code {$typeCode}.",
                ['code' => $code, 'type' => $typeCode],
            );
        }

        $pcode = $matches[2].'-'.$matches[3];
        $sizeSuffix = $matches[4] ?? '';
        $sizeTag = $this->resolveGlueSize($sizeSuffix);

        return $this->finalizeManufacturedParse(
            $item,
            $code,
            $typeCode,
            $pcode,
            $sizeTag,
            legacyCode: $code,
        );
    }

    protected function finalizeManufacturedParse(
        Item $item,
        string $originalCode,
        string $typeCode,
        string $pcode,
        ?Tag $sizeTag,
        ?string $legacyCode = null,
    ): LegacyParseResult {
        try {
            $this->identityBuilder->validatePcode(ItemType::ITEM, $pcode);
        } catch (InvalidArgumentException $e) {
            return LegacyParseResult::failure(
                self::FAILURE_PCODE_INVALID,
                $e->getMessage(),
                ['code' => $originalCode, 'pcode' => $pcode],
            );
        }

        $warnaResolution = $this->resolveManufacturedWarna($item, $pcode);

        if (! $warnaResolution['success']) {
            return LegacyParseResult::failure(
                $warnaResolution['code'],
                $warnaResolution['detail'],
                ['code' => $originalCode, 'pcode' => $pcode],
            );
        }

        $warnaTag = $warnaResolution['tag'];
        $typeTag = $this->typeTagsByCode->get($typeCode);

        $canonicalCode = $this->identityBuilder->buildCode(
            ItemType::ITEM,
            $pcode,
            $typeTag,
            $warnaTag,
            $sizeTag,
        );

        $groupName = $this->deriveManufacturedGroupName($item, $pcode);

        return LegacyParseResult::success(
            pcode: $pcode,
            typeCode: $typeCode,
            warnaCode: strtoupper($warnaTag->code),
            sizeCode: $sizeTag ? strtoupper((string) $sizeTag->code) : null,
            canonicalCode: $canonicalCode,
            groupName: $groupName,
            legacyCode: $legacyCode,
            codeUnchanged: $canonicalCode === $originalCode,
            snapshot: [
                'original_code' => $originalCode,
                'glue' => $legacyCode !== null,
            ],
        );
    }

    /**
     * @return array{success: bool, code?: string, detail?: string, tag?: Tag}
     */
    protected function resolveManufacturedWarna(Item $item, string $pcode): array
    {
        $existing = $item->relationLoaded('tags')
            ? $item->tags->firstWhere('type', Tag::TYPE_WARNA)
            : $item->tags()->where('type', Tag::TYPE_WARNA)->first();

        if ($existing) {
            return ['success' => true, 'tag' => $existing];
        }

        $variant = $this->identityBuilder->parsePcode(ItemType::ITEM, $pcode)['variant'] ?? '';

        if ($variant !== '' && $this->warnaTagsByCode->has(strtoupper($variant))) {
            return ['success' => true, 'tag' => $this->warnaTagsByCode->get(strtoupper($variant))];
        }

        $colorScan = $this->scanBahasaColor(
            trim((string) $item->description).' '.trim((string) $item->description2)
        );

        if ($colorScan['ambiguous']) {
            return [
                'success' => false,
                'code' => self::FAILURE_COLOR_AMBIGUOUS,
                'detail' => 'Multiple Bahasa colors found in description.',
            ];
        }

        if ($colorScan['code'] === null) {
            return [
                'success' => false,
                'code' => self::FAILURE_COLOR_NOT_FOUND,
                'detail' => 'No warna tag on item and no color found in description.',
            ];
        }

        return ['success' => true, 'tag' => $this->resolveWarnaTag($colorScan['code'])];
    }

    /**
     * @return array{code: ?string, ambiguous: bool}
     */
    public function scanBahasaColor(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        foreach (self::BAHASA_COLOR_MAP as $phrase => $code) {
            $pattern = '/\b'.preg_quote($phrase, '/').'\b/u';

            if (preg_match($pattern, $text)) {
                $found[$code] = true;
            }
        }

        $codes = array_keys($found);

        if (count($codes) > 1) {
            return ['code' => null, 'ambiguous' => true];
        }

        if (count($codes) === 1) {
            return ['code' => $codes[0], 'ambiguous' => false];
        }

        return ['code' => null, 'ambiguous' => false];
    }

    protected function resolveGlueSize(string $suffix): ?Tag
    {
        $suffix = strtoupper(trim($suffix));

        if ($suffix === '') {
            return $this->allSizeTag;
        }

        $normalized = self::SIZE_ALIASES[$suffix] ?? $suffix;

        $direct = $this->sizeTags->first(fn (Tag $tag) => strtoupper((string) $tag->code) === $normalized);

        if ($direct) {
            return $direct;
        }

        return $this->matchSizeFromSuffix($normalized) ?? $this->ensureSizeTag($normalized);
    }

    protected function ensureSizeTag(string $code): Tag
    {
        $code = strtoupper(trim($code));

        $existing = $this->sizeTags->first(fn (Tag $tag) => strtoupper((string) $tag->code) === $code);

        if ($existing) {
            return $existing;
        }

        $tag = Tag::query()->firstOrCreate(
            ['type' => Tag::TYPE_SIZE, 'code' => $code],
            ['name' => $code, 'item_type' => 0],
        );

        $this->sizeTags->prepend($tag);

        return $tag;
    }

    protected function resolveWarnaTag(string $warnaCode): Tag
    {
        $warnaCode = strtoupper(trim($warnaCode));

        if ($this->warnaTagsByCode->has($warnaCode)) {
            return $this->warnaTagsByCode->get($warnaCode);
        }

        if (! $this->isValidAutoCreateWarnaCode($warnaCode)) {
            throw new InvalidArgumentException("Invalid warna code: {$warnaCode}");
        }

        $attributes = Tag::normalizeWarnaAttributes([
            'type' => Tag::TYPE_WARNA,
            'name' => $compact !== '' ? $compact : $warnaCode,
            'code' => $compact !== '' ? $compact : $warnaCode,
            'item_type' => 0,
        ]);

        $tag = Tag::query()->firstOrCreate(
            ['type' => Tag::TYPE_WARNA, 'code' => $attributes['code']],
            ['name' => $attributes['name'], 'item_type' => 0],
        );

        $this->warnaTagsByCode->put(strtoupper($tag->code), $tag);

        return $tag;
    }

    protected function extractWarnaFromRemainder(string $remainder, ?Tag $sizeTag): string
    {
        $remainder = strtoupper(trim($remainder));

        if ($sizeTag) {
            $code = strtoupper((string) $sizeTag->code);

            if ($remainder === $code) {
                return '';
            }

            if (str_ends_with($remainder, '-'.$code)) {
                $remainder = substr($remainder, 0, -strlen('-'.$code));
            }
        }

        $remainder = trim((string) $remainder, '-');

        if ($remainder === '') {
            return '';
        }

        if ($this->warnaTagsByCode->has($remainder)) {
            return $remainder;
        }

        foreach ($this->warnaTagsByCodeLength as $tag) {
            $code = strtoupper((string) $tag->code);

            if ($remainder === $code || str_starts_with($remainder, $code.'-')) {
                return $code;
            }
        }

        $firstSegment = explode('-', $remainder, 2)[0];
        $mapped = $this->mapBahasaColorToken($firstSegment);

        if ($mapped !== null) {
            return $mapped;
        }

        if (preg_match('/^[A-Z]+$/', $remainder)) {
            return $remainder;
        }

        if (! preg_match('/[0-9]/', $remainder) && str_contains($remainder, '-')) {
            $joined = str_replace('-', '', $remainder);

            if ($this->warnaTagsByCode->has($joined)) {
                return $joined;
            }

            if (preg_match('/^[A-Z]+$/', $joined)) {
                return $joined;
            }
        }

        return '';
    }

    protected function mapBahasaColorToken(string $token): ?string
    {
        $token = mb_strtolower(trim($token));

        return self::BAHASA_COLOR_MAP[$token] ?? null;
    }

    protected function isValidAutoCreateWarnaCode(string $code): bool
    {
        return (bool) preg_match('/^[A-Z]+$/', $code);
    }

    protected function deriveAssetGroupName(Item $item, string $pcode): string
    {
        $name = trim((string) $item->name);

        if ($name !== '' && str_contains($name, ' - ')) {
            return strtoupper(trim(explode(' - ', $name, 2)[0]));
        }

        if ($name !== '') {
            return strtoupper($name);
        }

        return strtoupper($pcode);
    }

    protected function deriveManufacturedGroupName(Item $item, string $pcode): string
    {
        $existing = strtoupper(trim((string) ($item->group?->name ?? '')));

        if ($existing !== '' && strtoupper($existing) !== strtoupper($pcode)) {
            return $existing;
        }

        $name = trim((string) $item->name);

        if ($name !== '' && str_contains($name, ' - ')) {
            return strtoupper(trim(explode(' - ', $name, 2)[0]));
        }

        if ($name !== '') {
            return strtoupper($name);
        }

        return $this->identityBuilder->manufacturedParentMaster($item) ?: strtoupper($pcode);
    }
}
