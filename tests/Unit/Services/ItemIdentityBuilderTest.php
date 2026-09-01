<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->builder = new ItemIdentityBuilder;

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);

    $this->sizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'S',
        'name' => 'Small',
    ]);

    $this->warnaTag = Tag::factory()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => 'BLUE',
        'name' => 'BLUE',
    ]);

    $this->allSizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'AS',
        'name' => 'All Size',
    ]);
});

describe('pcode validation', function () {
    it('accepts manufactured item pcode', function () {
        $this->builder->validatePcode(ItemType::ITEM, 'CX90233-23');

        expect(true)->toBeTrue();
    });

    it('rejects invalid manufactured item pcode', function () {
        $this->builder->validatePcode(ItemType::ITEM, 'INVALID');
    })->throws(InvalidArgumentException::class);

    it('accepts asset lancar pcode', function () {
        $this->builder->validatePcode(ItemType::ASSET_LANCAR, 'GLOVE-01');

        expect(true)->toBeTrue();
    });

    it('accepts three-segment asset lancar pcode', function () {
        $this->builder->validatePcode(ItemType::ASSET_LANCAR, 'BAG-16-03');

        expect(true)->toBeTrue();
    });

    it('rejects invalid asset lancar pcode', function () {
        $this->builder->validatePcode(ItemType::ASSET_LANCAR, 'GLOVE');
    })->throws(InvalidArgumentException::class);
});

describe('buildCode', function () {
    it('builds manufactured item code without color segment', function () {
        $code = $this->builder->buildCode(
            ItemType::ITEM,
            'CX90324-05',
            $this->typeTag,
            $this->warnaTag,
            $this->sizeTag,
        );

        expect($code)->toBe('AJD-CX90324-05-S');
    });

    it('omits size segment for all-size tag on manufactured items', function () {
        $code = $this->builder->buildCode(
            ItemType::ITEM,
            'CX90324-05',
            $this->typeTag,
            $this->warnaTag,
            $this->allSizeTag,
        );

        expect($code)->toBe('AJD-CX90324-05');
    });

    it('builds asset lancar code with color and size', function () {
        $code = $this->builder->buildCode(
            ItemType::ASSET_LANCAR,
            'GLOVE-01',
            null,
            $this->warnaTag,
            $this->sizeTag,
        );

        expect($code)->toBe('GLOVE-01-BLUE-S');
    });

    it('omits size segment for all-size tag on asset lancar', function () {
        $code = $this->builder->buildCode(
            ItemType::ASSET_LANCAR,
            'GLOVE-01',
            null,
            $this->warnaTag,
            $this->allSizeTag,
        );

        expect($code)->toBe('GLOVE-01-BLUE');
    });
});

describe('buildName', function () {
    it('builds display name from group name, color, and size', function () {
        $name = $this->builder->buildName(
            'SLASH RUNNING SHIRT',
            $this->warnaTag,
            $this->sizeTag,
        );

        expect($name)->toBe('SLASH RUNNING SHIRT - BLUE - S');
    });

    it('omits size name for all-size tag', function () {
        $name = $this->builder->buildName(
            'SLASH RUNNING SHIRT',
            $this->warnaTag,
            $this->allSizeTag,
        );

        expect($name)->toBe('SLASH RUNNING SHIRT - BLUE');
    });
});

describe('parsePcode', function () {
    it('parses manufactured item pcode into master and variant', function () {
        expect($this->builder->parsePcode(ItemType::ITEM, 'CX90233-23'))
            ->toBe(['master' => 'CX90233', 'variant' => '23']);
    });

    it('keeps asset lancar pcode as master', function () {
        expect($this->builder->parsePcode(ItemType::ASSET_LANCAR, 'GLOVE-01'))
            ->toBe(['master' => 'GLOVE-01', 'variant' => null]);
    });
});

describe('groupVariant', function () {
    it('uses pcode suffix for manufactured items', function () {
        expect($this->builder->groupVariant(ItemType::ITEM, 'CX90233-23', $this->warnaTag))
            ->toBe('23');
    });

    it('uses warna code for asset lancar', function () {
        expect($this->builder->groupVariant(ItemType::ASSET_LANCAR, 'GLOVE-01', $this->warnaTag))
            ->toBe('BLUE');
    });
});

describe('asset sku splitting', function () {
    it('splits two-segment asset pcodes', function () {
        expect($this->builder->splitAssetSku('GLOVE-01-BLACK-S'))
            ->toBe(['pcode' => 'GLOVE-01', 'remainder' => 'BLACK-S']);
    });

    it('splits three-segment asset pcodes before warna', function () {
        expect($this->builder->splitAssetSku('BAG-16-03-BLACK'))
            ->toBe(['pcode' => 'BAG-16-03', 'remainder' => 'BLACK']);
    });
});

describe('stored group names', function () {
    it('suffixes asset lancar group names with warna variant', function () {
        expect($this->builder->storedGroupName(
            ItemType::ASSET_LANCAR,
            'HIP THRUST PAD',
            'HIPTHRUST-02',
            'AQUAMARINE',
        ))->toBe('HIP THRUST PAD - AQUAMARINE');
    });

    it('derives product display name from stored asset group name', function () {
        expect($this->builder->productDisplayName(
            ItemType::ASSET_LANCAR,
            'HIP THRUST PAD - AQUAMARINE',
            'AQUAMARINE',
        ))->toBe('HIP THRUST PAD');
    });

    it('strips uniqueness suffixes and color from stored group names', function () {
        expect($this->builder->productDisplayName(
            ItemType::ASSET_LANCAR,
            'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
            'BLACKWHITE',
            'ELBOWSUPPORT-02',
        ))->toBe('ELBOW STRAP');

        expect($this->builder->productDisplayName(
            ItemType::ASSET_LANCAR,
            'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02) - BLACKWHITE',
            'BLACKWHITE',
            'ELBOWSUPPORT-02',
        ))->toBe('ELBOW STRAP');
    });

    it('builds item names as title color and optional size', function () {
        $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACKWHITE', 'name' => 'BLACKWHITE']);

        expect($this->builder->buildName(
            'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
            $warna,
            $this->allSizeTag,
        ))->toBe('ELBOW STRAP - BLACKWHITE');

        expect($this->builder->buildName(
            'ELBOW STRAP',
            $warna,
            $this->sizeTag,
        ))->toBe('ELBOW STRAP - BLACKWHITE - S');
    });

    it('fits stored group names to the production varchar(50) column', function () {
        $long = 'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02/BLACKWHITE)';

        expect(strlen($long))->toBeGreaterThan(ItemIdentityBuilder::GROUP_NAME_MAX_LENGTH)
            ->and(strlen($this->builder->fitStoredGroupName($long)))->toBe(ItemIdentityBuilder::GROUP_NAME_MAX_LENGTH)
            ->and($this->builder->fitStoredGroupName($long))->toBe('ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02/BLACKWHI');
    });

    it('disambiguates colliding asset group names with a master suffix that fits in 50 chars', function () {
        \App\Models\ItemGroup::factory()->create([
            'master' => 'ELBOWSTRAP-01',
            'variant' => 'BLACKWHITE',
            'name' => 'ELBOW STRAP - BLACKWHITE',
        ]);

        $name = $this->builder->uniqueStoredGroupName(
            'ELBOW STRAP - BLACKWHITE',
            'ELBOWSUPPORT-02',
            'BLACKWHITE',
        );

        expect($name)->toBe('ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)')
            ->and(strlen($name))->toBeLessThanOrEqual(ItemIdentityBuilder::GROUP_NAME_MAX_LENGTH)
            ->and($name)->not->toBe('ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02/BLACKWHITE)');
    });

    it('fits an unused long stored name to 50 characters', function () {
        $long = $this->builder->storedGroupName(
            ItemType::ASSET_LANCAR,
            'PREMIUM ADJUSTABLE ELBOW SUPPORT STRAP',
            'ELBOWSUPPORT-02',
            'BLACKWHITE',
        );

        expect(strlen($long))->toBeGreaterThan(ItemIdentityBuilder::GROUP_NAME_MAX_LENGTH);

        $fitted = $this->builder->uniqueStoredGroupName($long, 'ELBOWSUPPORT-02', 'BLACKWHITE');

        expect(strlen($fitted))->toBe(ItemIdentityBuilder::GROUP_NAME_MAX_LENGTH)
            ->and($fitted)->toBe($this->builder->fitStoredGroupName($long));
    });

    it('keeps the preferred name when the same master and variant already own it', function () {
        \App\Models\ItemGroup::factory()->create([
            'master' => 'ELBOWSUPPORT-02',
            'variant' => 'BLACKWHITE',
            'name' => 'ELBOW STRAP - BLACKWHITE',
        ]);

        expect($this->builder->uniqueStoredGroupName(
            'ELBOW STRAP - BLACKWHITE',
            'ELBOWSUPPORT-02',
            'BLACKWHITE',
        ))->toBe('ELBOW STRAP - BLACKWHITE');
    });
});

describe('parent grouping', function () {
    it('builds manufactured parent key and label', function () {
        $group = \App\Models\ItemGroup::factory()->create([
            'master' => 'CX93024',
            'variant' => '05',
            'name' => 'RUNNING SHIRT',
        ]);

        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ITEM,
            'pcode' => 'CX93024-05',
            'code' => 'AJD-CX93024-05-S',
        ]);
        $item->tags()->attach($this->typeTag->id);

        expect($this->builder->itemParentKey($item))->toBe('1:AJD:CX93024');
        expect($this->builder->itemParentLabel($item))->toBe('AJD CX93024');
    });

    it('builds asset lancar parent key and label', function () {
        $group = \App\Models\ItemGroup::factory()->create([
            'master' => 'GLOVE-01',
            'variant' => 'BLACK',
            'name' => 'BOXING GLOVE',
        ]);

        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ASSET_LANCAR,
            'pcode' => 'GLOVE-01',
            'code' => 'GLOVE-01-BLACK-S',
        ]);
        $item->tags()->attach($this->warnaTag->id);

        expect($this->builder->itemParentKey($item))->toBe('2:GLOVE-01');
        expect($this->builder->itemParentLabel($item))->toBe('GLOVE-01');
    });
});

describe('Item SKU resolution', function () {
    it('finds items by canonical code', function () {
        $item = Item::factory()->create([
            'code' => 'AJD-CX90324-05-S',
            'legacy_code' => 'OLD-SKU-1',
        ]);

        expect(Item::findBySku('AJD-CX90324-05-S')?->id)->toBe($item->id);
    });

    it('finds items by legacy code', function () {
        $item = Item::factory()->create([
            'code' => 'AJD-CX90324-05-S',
            'legacy_code' => 'OLD-SKU-1',
        ]);

        expect(Item::findBySku('OLD-SKU-1')?->id)->toBe($item->id);
    });

    it('batch resolves mixed canonical and legacy skus', function () {
        $itemA = Item::factory()->create(['code' => 'NEW-A', 'legacy_code' => 'OLD-A']);
        $itemB = Item::factory()->create(['code' => 'NEW-B', 'legacy_code' => 'OLD-B']);

        $resolved = Item::findManyBySkus(['OLD-A', 'NEW-B']);

        expect($resolved->get('OLD-A')?->id)->toBe($itemA->id)
            ->and($resolved->get('NEW-B')?->id)->toBe($itemB->id);
    });
});
