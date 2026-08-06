<?php

use App\Enums\ItemType;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('allows manufactured item create without product name', function () {
    $typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $this->actingAs($this->user)
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX93249-03',
            'price' => 150000,
            'tags' => [
                'types' => [$typeTag->id],
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.index'))
        ->assertSessionHas('success');
});

it('repopulates item create form after validation errors', function () {
    $this->actingAs($this->user)
        ->from(route('items.create'))
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'INVALID',
            'alias' => 'Slash Running Shirt',
            'price' => 150000,
            'description' => 'Test desc',
            'tags' => [
                'sizes' => [],
            ],
        ])
        ->assertRedirect(route('items.create'))
        ->assertSessionHasErrors();

    $this->actingAs($this->user)
        ->get(route('items.create'))
        ->assertOk()
        ->assertSee('data-testid="form-errors"', false)
        ->assertSee('Slash Running Shirt', false)
        ->assertSee('value="INVALID"', false)
        ->assertSee('Test desc', false);
});

it('repopulates item create form after service error', function () {
    $typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $this->actingAs($this->user)
        ->from(route('items.create'))
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'BAD',
            'alias' => 'Keep My Product Name',
            'price' => 99000,
            'tags' => [
                'types' => [$typeTag->id],
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.create'))
        ->assertSessionHasErrors('message');

    $this->actingAs($this->user)
        ->get(route('items.create'))
        ->assertOk()
        ->assertSee('Keep My Product Name', false)
        ->assertSee('value="BAD"', false)
        ->assertSee('data-testid="form-errors"', false);
});

it('renders asset lancar create page', function () {
    $this->actingAs($this->user)
        ->get(route('assetlancar.create'))
        ->assertOk()
        ->assertSee('Create New Asset', false)
        ->assertSee('Product Name', false);
});
