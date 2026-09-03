<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();

    $role = Role::create(['name' => 'super-admin']);
    $this->user->assignRole($role);
});

it('can view tags page', function () {
    $response = $this->actingAs($this->user)->get('/tags');

    $response->assertStatus(200);
});

it('lists tags with links to filtered item lists', function () {
    $tag = Tag::create([
        'name' => 'Linked Tag',
        'code' => 'LNK',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => ItemType::ITEM->value,
    ]);

    $this->actingAs($this->user)
        ->get('/tags')
        ->assertOk()
        ->assertSee($tag->itemsIndexFilterUrl(), false)
        ->assertSee('Linked Tag');
});

it('can create tag', function () {
    $response = $this->actingAs($this->user)->post('/tags', [
        'name' => 'Test Tag',
        'code' => 'TST',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tags', [
        'name' => 'Test Tag',
        'code' => 'TST',
        'type' => Tag::TYPE_TYPE,
    ]);
});

it('can update tag', function () {
    $tag = Tag::create([
        'name' => 'Old Tag',
        'code' => 'OLD',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $response = $this->actingAs($this->user)->put("/tags/{$tag->id}", [
        'name' => 'New Tag',
        'code' => 'NEW',
        'type' => Tag::TYPE_SIZE,
        'item_type' => 1,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => 'New Tag',
        'code' => 'NEW',
        'type' => Tag::TYPE_SIZE,
        'item_type' => 1,
    ]);
});

it('can delete tag', function () {
    $tag = Tag::create([
        'name' => 'To Delete',
        'code' => 'DEL',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $response = $this->actingAs($this->user)->delete("/tags/{$tag->id}");

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('tags', [
        'id' => $tag->id,
    ]);
});

it('filters tags list by tag type', function () {
    Tag::create([
        'name' => 'Size Tag',
        'code' => 'SZ',
        'type' => Tag::TYPE_SIZE,
        'item_type' => 0,
    ]);
    Tag::create([
        'name' => 'Type Tag',
        'code' => 'TP',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $this->actingAs($this->user)
        ->get('/tags?type='.Tag::TYPE_SIZE)
        ->assertOk()
        ->assertSee('Size Tag')
        ->assertDontSee('Type Tag');
});

it('shows the number of items tagged on the tags list', function () {
    $tag = Tag::create([
        'name' => 'Counted Tag',
        'code' => 'CNT',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);
    $otherTag = Tag::create([
        'name' => 'Other Tag',
        'code' => 'OTH',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);

    $itemOne = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => (string) $tag->id,
    ]);
    $itemTwo = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => (string) $tag->id,
    ]);
    $itemOne->tags()->sync([$tag->id]);
    $itemTwo->tags()->sync([$tag->id]);
    Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => (string) $otherTag->id,
    ])->tags()->sync([$otherTag->id]);

    $response = $this->actingAs($this->user)->get('/tags');

    $response->assertOk()
        ->assertSee('Counted Tag')
        ->assertSee(route('items.index', ['tag_ids' => [$tag->id]]), false)
        ->assertSee('>2</a>', false);
});

it('sorts tags list by column', function () {
    Tag::create([
        'name' => 'Alpha Tag',
        'code' => 'AAA',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);
    Tag::create([
        'name' => 'Zulu Tag',
        'code' => 'ZZZ',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);

    $this->actingAs($this->user)
        ->get('/tags?sort=name&direction=desc')
        ->assertOk()
        ->assertSeeInOrder(['Zulu Tag', 'Alpha Tag']);

    $this->actingAs($this->user)
        ->get('/tags?sort=code&direction=asc')
        ->assertOk()
        ->assertSeeInOrder(['Alpha Tag', 'Zulu Tag']);
});

it('sorts tags list by item count', function () {
    $lowTag = Tag::create([
        'name' => 'Low Count',
        'code' => 'LOW',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);
    $highTag = Tag::create([
        'name' => 'High Count',
        'code' => 'HIGH',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);

    $lowItem = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => (string) $lowTag->id,
    ]);
    $lowItem->tags()->sync([$lowTag->id]);

    $highOne = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => (string) $highTag->id,
    ]);
    $highTwo = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => (string) $highTag->id,
    ]);
    $highOne->tags()->sync([$highTag->id]);
    $highTwo->tags()->sync([$highTag->id]);

    $this->actingAs($this->user)
        ->get('/tags?sort=items_count&direction=desc')
        ->assertOk()
        ->assertSeeInOrder(['High Count', 'Low Count']);
});

it('untags items and asset lancar when a tag is deleted', function () {
    $tag = Tag::create([
        'name' => 'Detach Me',
        'code' => 'DTCH',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);
    $otherTag = Tag::create([
        'name' => 'Keep Me',
        'code' => 'KEEP',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => "{$otherTag->id},{$tag->id}",
    ]);
    $asset = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'tag_ids' => "{$tag->id},{$otherTag->id}",
    ]);

    $item->tags()->sync([$tag->id, $otherTag->id]);
    $asset->tags()->sync([$tag->id, $otherTag->id]);

    $this->actingAs($this->user)->delete("/tags/{$tag->id}")->assertSessionHasNoErrors();

    $item->refresh();
    $asset->refresh();

    expect($item->tag_ids)->toBe((string) $otherTag->id)
        ->and($asset->tag_ids)->toBe((string) $otherTag->id)
        ->and($item->tags()->pluck('tags.id')->all())->toBe([$otherTag->id])
        ->and($asset->tags()->pluck('tags.id')->all())->toBe([$otherTag->id])
        ->and(DB::table('item_tag')->where('tag_id', $tag->id)->count())->toBe(0)
        ->and(DB::table('item_tag')->where('tag_id', $otherTag->id)->count())->toBe(2);
});

it('removes item_tag pivot rows only for the deleted tag', function () {
    $deletedTag = Tag::create([
        'name' => 'Gone Tag',
        'code' => 'GONE',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);
    $keptTag = Tag::create([
        'name' => 'Stay Tag',
        'code' => 'STAY',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => "{$deletedTag->id},{$keptTag->id}",
    ]);
    $item->tags()->sync([$deletedTag->id, $keptTag->id]);

    $this->actingAs($this->user)->delete("/tags/{$deletedTag->id}")->assertSessionHasNoErrors();

    expect(DB::table('item_tag')->where('tag_id', $deletedTag->id)->count())->toBe(0)
        ->and(DB::table('item_tag')->where('tag_id', $keptTag->id)->count())->toBe(1);
});

it('clears legacy tag_ids when a tag is deleted without pivot rows', function () {
    $tag = Tag::create([
        'name' => 'Legacy Only',
        'code' => 'LEG',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);
    $otherTag = Tag::create([
        'name' => 'Other Legacy',
        'code' => 'OTH2',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => 0,
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => "{$otherTag->id},{$tag->id}",
        'genre' => $tag->id,
        'size' => $tag->id,
    ]);

    $this->actingAs($this->user)->delete("/tags/{$tag->id}")->assertSessionHasNoErrors();

    $item->refresh();

    expect($item->tag_ids)->toBe((string) $otherTag->id)
        ->and($item->genre)->toBe(0)
        ->and($item->size)->toBe(0);
});

it('clears item_group.genre when the type tag is deleted', function () {
    $tag = Tag::create([
        'name' => 'Group Genre',
        'code' => 'GGN',
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
    ]);
    $group = \App\Models\ItemGroup::factory()->create(['genre' => $tag->id]);
    Item::factory()->create([
        'group_id' => $group->id,
        'genre' => $tag->id,
    ]);

    $this->actingAs($this->user)->delete("/tags/{$tag->id}")->assertSessionHasNoErrors();

    expect($group->fresh()->genre)->toBe(0);
});

it('renders item and asset lancar show pages after a referenced tag is deleted', function () {
    $typeTag = Tag::create([
        'name' => 'Show Type',
        'code' => 'SHWT',
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
    ]);
    $warnaTag = Tag::create([
        'name' => 'SHOWRED',
        'code' => 'SHOWRED',
        'type' => Tag::TYPE_WARNA,
        'item_type' => 0,
    ]);
    $assetTypeTag = Tag::create([
        'name' => 'GLOVE',
        'code' => 'GLOVE',
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'tag_ids' => "{$typeTag->id},{$warnaTag->id}",
        'code' => 'SHWT-TEST-01-S',
    ]);
    $asset = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'tag_ids' => (string) $assetTypeTag->id,
        'code' => 'GLOVE-01-RED',
    ]);

    $item->tags()->sync([$typeTag->id, $warnaTag->id]);
    $asset->tags()->sync([$assetTypeTag->id]);

    $this->actingAs($this->user)->delete("/tags/{$warnaTag->id}")->assertSessionHasNoErrors();

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee($typeTag->name)
        ->assertDontSee($warnaTag->name);

    $this->actingAs($this->user)->delete("/tags/{$assetTypeTag->id}")->assertSessionHasNoErrors();

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $asset))
        ->assertOk()
        ->assertSee($asset->code);
});

it('links asset lancar tags to the asset lancar index', function () {
    $tag = Tag::create([
        'name' => 'Asset Tag',
        'code' => 'AST',
        'type' => Tag::TYPE_NORMAL,
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);

    expect($tag->itemsIndexFilterUrl())->toBe(route('assetlancar.index', ['tag_ids' => [$tag->id]]));
});
