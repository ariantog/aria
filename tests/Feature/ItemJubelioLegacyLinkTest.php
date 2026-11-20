<?php

use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows legacy link button only when legacy_code is set', function () {
    $withoutLegacy = Item::factory()->create([
        'code' => 'NEW-SKU-ONLY',
        'legacy_code' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.jubelio', $withoutLegacy->id))
        ->assertOk()
        ->assertSee('data-testid="jubelio-link-current-sku"', false)
        ->assertDontSee('data-testid="jubelio-link-legacy-sku"', false)
        ->assertDontSee('Link Legacy SKU', false);

    $withLegacy = Item::factory()->create([
        'code' => 'FABRICBAND-03-BABYBLUE-LIGHT',
        'legacy_code' => 'FABRICBAND-03-LIGHT-BABYBLUE',
    ]);

    $legacyUrl = route('items.jubelio-search', [
        'item' => $withLegacy->id,
        'q' => 'FABRICBAND-03-LIGHT-BABYBLUE',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.jubelio', $withLegacy->id))
        ->assertOk()
        ->assertSee('data-testid="jubelio-link-legacy-sku"', false)
        ->assertSee('Link Legacy SKU', false)
        ->assertSee($legacyUrl, false);
});

it('hides legacy link button when legacy_code is blank whitespace', function () {
    $item = Item::factory()->create([
        'code' => 'HAS-CODE',
        'legacy_code' => '   ',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.jubelio', $item->id))
        ->assertOk()
        ->assertDontSee('Link Legacy SKU', false);
});
