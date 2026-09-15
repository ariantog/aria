<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\ItemGroupHierarchyService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->hierarchy = app(ItemGroupHierarchyService::class);

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'POWERBAND',
        'name' => 'Powerband',
    ]);
    $this->extraTag = Tag::factory()->create(['type' => Tag::TYPE_NORMAL, 'code' => 'EXTRA', 'name' => 'Extra']);

    $this->sizeTags = collect([
        'XXLIGHT' => Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'XXLIGHT', 'name' => 'XXLIGHT']),
        'XLIGHT' => Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'XLIGHT', 'name' => 'XLIGHT']),
        'LIGHT' => Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'LIGHT', 'name' => 'LIGHT']),
        'MEDIUM' => Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'MEDIUM', 'name' => 'MEDIUM']),
        'HEAVY' => Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'HEAVY', 'name' => 'HEAVY']),
    ]);
    $this->blackTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
});

it('parent detail lists every sku in a single asset group with strength sizes', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'POWERBAND-03',
        'variant' => 'BLACK',
        'name' => 'CORE 2080MM WORKOUT BAND',
    ]);

    foreach ($this->sizeTags as $strength => $sizeTag) {
        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ASSET_LANCAR,
            'pcode' => 'POWERBAND-03',
            'code' => 'POWERBAND-03-BLACK-'.$strength,
            'name' => 'CORE 2080MM WORKOUT BAND - BLACK - '.$strength,
        ]);
        $item->tags()->attach([
            $this->typeTag->id,
            $this->blackTag->id,
            $sizeTag->id,
            $this->extraTag->id,
        ]);
    }

    $detail = $this->hierarchy->parentDetail('2:POWERBAND-03', fetchJubelio: false);

    $rows = collect($detail['colors'])->flatMap(fn (array $c) => $c['size_rows']);

    expect($rows)->toHaveCount(5);
});

it('parent detail page shows all skus when warehouse qty is zero', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'POWERBAND-03',
        'variant' => 'BLACK',
        'name' => 'CORE 2080MM WORKOUT BAND',
    ]);

    foreach ($this->sizeTags as $strength => $sizeTag) {
        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ASSET_LANCAR,
            'pcode' => 'POWERBAND-03',
            'code' => 'POWERBAND-03-BLACK-'.$strength,
        ]);
        $item->tags()->attach([$this->typeTag->id, $this->blackTag->id, $sizeTag->id]);
    }

    $response = $this->actingAs($this->user)
        ->get(route('items.group-parent-detail', $group->id));

    $response->assertOk()
        ->assertSee('POWERBAND-03-BLACK-MEDIUM', false)
        ->assertSee('POWERBAND-03-BLACK-HEAVY', false)
        ->assertSee('POWERBAND-03-BLACK-LIGHT', false)
        ->assertSee('POWERBAND-03-BLACK-XXLIGHT', false)
        ->assertSee('POWERBAND-03-BLACK-XLIGHT', false);
});

it('redirects legacy slug parent urls to numeric group id', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'POWERBAND-03',
        'variant' => 'BLACK',
        'name' => 'CORE 2080MM WORKOUT BAND',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'POWERBAND-03',
        'code' => 'POWERBAND-03-BLACK-MEDIUM',
    ]);
    $item->tags()->attach([$this->blackTag->id, $this->sizeTags['MEDIUM']->id]);

    $this->actingAs($this->user)
        ->get(route('items.group-parent-legacy', '2__POWERBAND-03'))
        ->assertRedirect(route('items.group-parent-detail', $group->id));
});
