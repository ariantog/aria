<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
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
        ->and($asset->tags()->pluck('tags.id')->all())->toBe([$otherTag->id]);
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
