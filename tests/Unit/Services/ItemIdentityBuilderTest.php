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
