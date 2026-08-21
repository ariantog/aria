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
