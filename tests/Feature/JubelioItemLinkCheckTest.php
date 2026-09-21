<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('jubelio-view', 'web');

    // First user gets id 1 (superadmin bypass). Burn id 1 for permission tests.
    User::factory()->create();
});

it('renders jubelio item links index for group view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $this->actingAs($user)
        ->get(route('jubelio.item-links.index'))
        ->assertSuccessful()
        ->assertSee('Jubelio Item Links')
        ->assertSee('Per grup')
        ->assertSee('Per SKU');
});

it('filters items by link status on sku view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $linked = Item::factory()->create([
        'code' => 'JUBCHK-LINKED-01',
        'jubelio_item_id' => 99901,
    ]);
    $unlinked = Item::factory()->create([
        'code' => 'JUBCHK-NOLINK-01',
        'jubelio_item_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.item-links.index', ['view' => 'items', 'link' => 'unlinked', 'q' => 'JUBCHK-NOLINK']))
        ->assertSuccessful()
        ->assertSee('JUBCHK-NOLINK-01', false)
        ->assertDontSee('JUBCHK-LINKED-01', false);

    $this->actingAs($user)
        ->get(route('jubelio.item-links.index', ['view' => 'items', 'link' => 'linked', 'q' => 'JUBCHK-LINKED']))
        ->assertSuccessful()
        ->assertSee('JUBCHK-LINKED-01', false)
        ->assertDontSee('JUBCHK-NOLINK-01', false);
});

it('shows per-group sku link breakdown', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('jubelio-view');

    $group = ItemGroup::factory()->create([
        'master' => 'CX99999-01',
        'variant' => '01',
        'name' => 'TEST GROUP LINK',
    ]);

    Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'GRP-LINKED-SKU',
        'pcode' => 'CX99999-01',
        'jubelio_item_id' => 12345,
    ]);
    Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'GRP-UNLINKED-SKU',
        'pcode' => 'CX99999-01',
        'jubelio_item_id' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.item-links.group', $group->id))
        ->assertSuccessful()
        ->assertSee('GRP-LINKED-SKU')
        ->assertSee('GRP-UNLINKED-SKU')
        ->assertSee('Linked')
        ->assertSee('Not linked');
});

it('forbids jubelio item links without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jubelio.item-links.index'))
        ->assertForbidden();
});
