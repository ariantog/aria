<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders the catalog mock index with scenario cards', function () {
    $this->actingAs($this->user)
        ->get(route('items.catalog-mock.index'))
        ->assertOk()
        ->assertSee('Compare catalog UI mockups', false)
        ->assertSee('D · Live + labels', false)
        ->assertSee('data-testid="mock-scenario-no-marketing-title"', false)
        ->assertSee('data-testid="catalog-ui-mock-banner"', false);
});
