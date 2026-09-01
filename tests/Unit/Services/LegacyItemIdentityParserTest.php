<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Items\LegacyItemIdentityParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->builder = new ItemIdentityBuilder;

    $this->sizeTags = collect([
        Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']),
        Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'XL', 'name' => 'XL']),
        Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '14OZ', 'name' => '14OZ']),
        Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '3M', 'name' => '3M']),
        Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'SM', 'name' => 'SM']),
        Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'AS', 'name' => 'All Size']),
    ]);

    $this->warnaTags = collect([
        Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']),
        Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'WHITE', 'name' => 'WHITE']),
        Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'POWDERWHITE', 'name' => 'POWDERWHITE']),
        Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'MARBLEPINK', 'name' => 'MARBLEPINK']),
        Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']),
    ]);

    $this->typeTags = collect([
        Tag::factory()->create([
            'type' => Tag::TYPE_TYPE,
            'item_type' => ItemType::ITEM->value,
            'code' => 'AJJ',
            'name' => 'Jacket',
        ]),
    ]);

    $this->parser = new LegacyItemIdentityParser(
        $this->builder,
        $this->sizeTags,
        $this->warnaTags,
        $this->typeTags,
        $this->sizeTags->firstWhere('code', 'AS'),
    );
});

function makeAssetItem(string $code, ?string $name = null): Item
{
    return Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => null,
        'code' => $code,
        'pcode' => explode('-', $code)[0].'-'.explode('-', $code)[1],
        'name' => $name ?? $code,
    ]);
}

describe('size matching', function () {
    it('matches longest hyphenated size suffix first', function () {
        $match = $this->parser->matchSizeFromSuffix('MARBLEPINK-3M');

        expect($match?->code)->toBe('3M');
    });

    it('matches exact size remainder', function () {
        expect($this->parser->matchSizeFromSuffix('XL')?->code)->toBe('XL');
    });
});

describe('asset fixtures §3.2', function () {
    it('parses YOGAMAT-20-POWDERWHITE as all-size without code change', function () {
        $item = makeAssetItem('YOGAMAT-20-POWDERWHITE', 'YOGA MAT - POWDERWHITE');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->pcode)->toBe('YOGAMAT-20')
            ->and($result->warnaCode)->toBe('POWDERWHITE')
            ->and($result->sizeCode)->toBe('AS')
            ->and($result->canonicalCode)->toBe('YOGAMAT-20-POWDERWHITE')
            ->and($result->codeUnchanged)->toBeTrue();
    });

    it('parses LUMBARSUPPORT-01-BLACK-S unchanged', function () {
        $item = makeAssetItem('LUMBARSUPPORT-01-BLACK-S', 'LUMBAR SUPPORT - BLACK - S');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->pcode)->toBe('LUMBARSUPPORT-01')
            ->and($result->warnaCode)->toBe('BLACK')
            ->and($result->sizeCode)->toBe('S')
            ->and($result->canonicalCode)->toBe('LUMBARSUPPORT-01-BLACK-S')
            ->and($result->codeUnchanged)->toBeTrue();
    });

    it('parses BOXINGGLOVE-02-BLACK-14OZ unchanged', function () {
        $item = makeAssetItem('BOXINGGLOVE-02-BLACK-14OZ', 'BOXING GLOVE - BLACK - 14OZ');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->pcode)->toBe('BOXINGGLOVE-02')
            ->and($result->warnaCode)->toBe('BLACK')
            ->and($result->sizeCode)->toBe('14OZ')
            ->and($result->canonicalCode)->toBe('BOXINGGLOVE-02-BLACK-14OZ')
            ->and($result->codeUnchanged)->toBeTrue();
    });

    it('parses BOXINGWRAP-02-MARBLEPINK-3M unchanged', function () {
        $item = makeAssetItem('BOXINGWRAP-02-MARBLEPINK-3M', 'BOXING WRAP - MARBLEPINK - 3M');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->pcode)->toBe('BOXINGWRAP-02')
            ->and($result->warnaCode)->toBe('MARBLEPINK')
            ->and($result->sizeCode)->toBe('3M')
            ->and($result->canonicalCode)->toBe('BOXINGWRAP-02-MARBLEPINK-3M')
            ->and($result->codeUnchanged)->toBeTrue();
    });

    it('parses BAG-16-03-BLACK with a three-segment pcode', function () {
        $item = Item::factory()->create([
            'type' => ItemType::ASSET_LANCAR,
            'group_id' => null,
            'code' => 'BAG-16-03-BLACK',
            'pcode' => 'BAG-16-03',
            'name' => 'BAG - BLACK',
        ]);
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->pcode)->toBe('BAG-16-03')
            ->and($result->warnaCode)->toBe('BLACK')
            ->and($result->canonicalCode)->toBe('BAG-16-03-BLACK')
            ->and($result->codeUnchanged)->toBeTrue();
    });
});

describe('manufactured glue fixture §4.5', function () {
    it('parses AJJPL2512906XL into AJJ-PL25129-06-XL', function () {
        $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);

        $item = Item::factory()->create([
            'type' => ItemType::ITEM,
            'group_id' => null,
            'code' => 'AJJPL2512906XL',
            'pcode' => 'PL25129-06',
            'name' => 'JACKET',
        ]);
        $item->tags()->sync([
            $this->typeTags->first()->id,
            $warna->id,
            Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1'])->id,
        ]);

        $result = $this->parser->parse($item->fresh('tags'));

        expect($result->success)->toBeTrue()
            ->and($result->typeCode)->toBe('AJJ')
            ->and($result->pcode)->toBe('PL25129-06')
            ->and($result->sizeCode)->toBe('XL')
            ->and($result->canonicalCode)->toBe('AJJ-PL25129-06-XL')
            ->and($result->legacyCode)->toBe('AJJPL2512906XL');
    });

    it('parses hyphenated manufactured code AJJ-PL25129-06-XL', function () {
        $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);

        $item = Item::factory()->create([
            'type' => ItemType::ITEM,
            'code' => 'AJJ-PL25129-06-XL',
            'pcode' => 'PL25129-06',
        ]);
        $item->tags()->sync([
            $this->typeTags->first()->id,
            $warna->id,
        ]);

        $result = $this->parser->parse($item->fresh('tags'));

        expect($result->success)->toBeTrue()
            ->and($result->canonicalCode)->toBe('AJJ-PL25129-06-XL');
    });
});

describe('minimum identity structure', function () {
    it('rejects asset codes without a color segment', function () {
        expect($this->parser->hasMinimumIdentityStructure('HANGER-01', ItemType::ASSET_LANCAR))->toBeFalse();
    });

    it('rejects asset codes with only a size suffix and no color', function () {
        expect($this->parser->hasMinimumIdentityStructure('ECOFEET-13-SM', ItemType::ASSET_LANCAR))->toBeFalse();
    });

    it('accepts asset codes with pcode color and optional size', function () {
        expect($this->parser->hasMinimumIdentityStructure('GLOVE-01-BLACK-S', ItemType::ASSET_LANCAR))->toBeTrue();
    });

    it('accepts manufactured hyphenated type and pcode', function () {
        expect($this->parser->hasMinimumIdentityStructure('AJJ-PL25129-06-XL', ItemType::ITEM))->toBeTrue();
    });

    it('rejects manufactured codes without type and pcode segments', function () {
        expect($this->parser->hasMinimumIdentityStructure('HANGER-01', ItemType::ITEM))->toBeFalse();
    });

    it('accepts manufactured glue codes', function () {
        expect($this->parser->hasMinimumIdentityStructure('AJJPL2512906XL', ItemType::ITEM))->toBeTrue();
    });
});

describe('unsupported item types', function () {
    it('returns failure for asset tetap without reading value on int', function () {
        $item = Item::factory()->make([
            'type' => ItemType::ASSET_TETAP->value,
            'code' => 'FOO-01-BAR',
        ]);

        $result = $this->parser->parse($item);

        expect($result->success)->toBeFalse()
            ->and($result->failureCode)->toBe(LegacyItemIdentityParser::FAILURE_SKU_UNPARSEABLE)
            ->and($result->snapshot['type'])->toBe(ItemType::ASSET_TETAP->value);
    });

    it('parses manufactured items when type is stored as int', function () {
        $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);

        $item = Item::factory()->create([
            'type' => ItemType::ITEM->value,
            'code' => 'AJJ-PL25129-06-XL',
            'pcode' => 'PL25129-06',
        ]);
        $item->tags()->sync([
            $this->typeTags->first()->id,
            $warna->id,
        ]);

        $result = $this->parser->parse($item->fresh('tags'));

        expect($result->success)->toBeTrue()
            ->and($result->canonicalCode)->toBe('AJJ-PL25129-06-XL');
    });
});

describe('Bahasa color scan', function () {
    it('detects Indonesian color words', function () {
        expect($this->parser->scanBahasaColor('warna hitam pekat'))->toBe([
            'code' => 'BLACK',
            'ambiguous' => false,
        ]);
    });

    it('flags ambiguous colors', function () {
        $scan = $this->parser->scanBahasaColor('hitam dan merah');

        expect($scan['ambiguous'])->toBeTrue();
    });
});

describe('warna extraction from legacy asset remainders', function () {
    it('maps PUTIH hardware suffix to WHITE instead of creating junk tag', function () {
        $item = makeAssetItem('ZIPPER-01-PUTIH-6-PA', 'ZIPPER - PUTIH 6 PA');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->warnaCode)->toBe('WHITE')
            ->and($result->canonicalCode)->toBe('ZIPPER-01-WHITE');
    });

    it('maps HITAM hardware suffix to BLACK', function () {
        $item = makeAssetItem('ZIPPER-02-HITAM-45-YKK', 'ZIPPER - HITAM 45 YKK');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->warnaCode)->toBe('BLACK');
    });

    it('rejects non-color remainders like XS-01-NT', function () {
        $item = makeAssetItem('PRODUCT-01-XS-01-NT', 'PRODUCT - XS-01 NT');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeFalse()
            ->and($result->failureCode)->toBe(LegacyItemIdentityParser::FAILURE_WARNA_MISSING);
    });
});

describe('warna tag name uniqueness', function () {
    it('reuses an existing GREYWHITE tag whose code differs', function () {
        $existing = Tag::withoutEvents(fn () => Tag::query()->create([
            'type' => Tag::TYPE_WARNA,
            'code' => 'GW',
            'name' => 'GREYWHITE',
            'item_type' => 0,
        ]));

        $parser = new LegacyItemIdentityParser(
            $this->builder,
            $this->sizeTags,
            $this->warnaTags->push($existing),
            $this->typeTags,
            $this->sizeTags->firstWhere('code', 'AS'),
        );

        $item = makeAssetItem('YOGASTRAP-01-GREYWHITE', 'YOGA STRAP - GREYWHITE');
        $result = $parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->warnaCode)->toBe('GW')
            ->and(Tag::query()->whereRaw('UPPER(TRIM(name)) = ?', ['GREYWHITE'])->count())->toBe(1)
            ->and(Tag::query()->where('name', 'GREYWHITE')->where('type', Tag::TYPE_WARNA)->first()?->id)
            ->toBe($existing->id);
    });

    it('does not insert GREYWHITE when that name belongs to another tag type', function () {
        Tag::factory()->create([
            'type' => Tag::TYPE_NORMAL,
            'code' => 'OTHER',
            'name' => 'GREYWHITE',
            'item_type' => 0,
        ]);

        $parser = new LegacyItemIdentityParser(
            $this->builder,
            $this->sizeTags,
            $this->warnaTags,
            $this->typeTags,
            $this->sizeTags->firstWhere('code', 'AS'),
        );

        $item = makeAssetItem('YOGASTRAP-01-GREYWHITE', 'YOGA STRAP - GREYWHITE');
        $result = $parser->parse($item);

        expect($result->success)->toBeFalse()
            ->and($result->failureCode)->toBe(LegacyItemIdentityParser::FAILURE_COLOR_NOT_FOUND)
            ->and(Tag::query()->whereRaw('UPPER(TRIM(name)) = ?', ['GREYWHITE'])->count())->toBe(1);
    });
});

describe('fabricband size-before-color leftovers', function () {
    beforeEach(function () {
        $this->sizeTags = $this->sizeTags->concat(collect([
            Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'LIGHT', 'name' => 'LIGHT']),
            Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'MEDIUM', 'name' => 'MEDIUM']),
            Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'HEAVY', 'name' => 'HEAVY']),
        ]))->sortByDesc(fn (Tag $tag) => strlen((string) $tag->code))->values();

        $this->warnaTags = $this->warnaTags->concat(collect([
            Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BABYBLUE', 'name' => 'BABYBLUE']),
            Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'GREEN', 'name' => 'GREEN']),
            Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'GRAY', 'name' => 'GRAY']),
        ]));

        $this->parser = new LegacyItemIdentityParser(
            $this->builder,
            $this->sizeTags,
            $this->warnaTags,
            $this->typeTags,
            $this->sizeTags->firstWhere('code', 'AS'),
        );
    });

    it('rewrites legacy size-before-color fabricband skus', function () {
        $item = makeAssetItem('FABRICBAND-03-LIGHT-BABYBLUE', 'FABRIC BAND LIGHT - BABYBLUE');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->pcode)->toBe('FABRICBAND-03')
            ->and($result->warnaCode)->toBe('BABYBLUE')
            ->and($result->sizeCode)->toBe('LIGHT')
            ->and($result->canonicalCode)->toBe('FABRICBAND-03-BABYBLUE-LIGHT')
            ->and($result->groupName)->toBe('FABRIC BAND')
            ->and($result->codeUnchanged)->toBeFalse();
    });

    it('keeps already-canonical fabricband color-before-size skus', function () {
        $item = makeAssetItem('FABRICBAND-03-GREEN-HEAVY', 'FABRIC BAND HEAVY - GREEN - HEAVY');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->warnaCode)->toBe('GREEN')
            ->and($result->sizeCode)->toBe('HEAVY')
            ->and($result->canonicalCode)->toBe('FABRICBAND-03-GREEN-HEAVY')
            ->and($result->groupName)->toBe('FABRIC BAND')
            ->and($result->codeUnchanged)->toBeTrue();
    });

    it('resolves GREY via the GRAY warna alias', function () {
        $item = makeAssetItem('FABRICBAND-03-HEAVY-GREY', 'FABRIC BAND HEAVY - GREY');
        $result = $this->parser->parse($item);

        expect($result->success)->toBeTrue()
            ->and($result->warnaCode)->toBe('GRAY')
            ->and($result->canonicalCode)->toBe('FABRICBAND-03-GRAY-HEAVY');
    });
});
