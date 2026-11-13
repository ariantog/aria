<?php

use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate(Jubelio::getPermissions()['view'], 'web');
    Permission::findOrCreate(Jubelio::getPermissions()['delete'], 'web');
});

it('forbids deleting jubelio queue row without delete permission', function () {
    User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Jubelio::getPermissions()['view']);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'del-forbidden',
        'source' => 3,
        'invoice' => 'SP-WRONG-SCAN',
        'type' => 'SELL',
        'order_status' => 'READY TO SHIP',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($viewer)
        ->delete(route('jubelio.destroy', $order))
        ->assertForbidden();

    expect(Jubelioorder::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('deletes jubelio queue row when permitted and no aria transaction', function () {
    User::factory()->create();
    $deleter = User::factory()->create();
    $deleter->givePermissionTo([Jubelio::getPermissions()['view'], Jubelio::getPermissions()['delete']]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'del-ok',
        'source' => 3,
        'invoice' => 'SP-DELETE-ME',
        'type' => 'SELL',
        'order_status' => 'READY TO SHIP',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($deleter)
        ->from(route('jubelio.show', $order))
        ->delete(route('jubelio.destroy', $order))
        ->assertRedirect(route('jubelio.index'))
        ->assertSessionHas('success');

    expect(Jubelioorder::query()->whereKey($order->id)->exists())->toBeFalse();
});

it('blocks deleting jubelio queue row when aria transaction exists', function () {
    User::factory()->create();
    $deleter = User::factory()->create();
    $deleter->givePermissionTo([Jubelio::getPermissions()['view'], Jubelio::getPermissions()['delete']]);

    Transaction::factory()->create([
        'invoice' => 'SP-HAS-TXN',
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'del-blocked',
        'source' => 3,
        'invoice' => 'SP-HAS-TXN',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 2,
        'error_type' => 10,
    ]);

    $this->actingAs($deleter)
        ->delete(route('jubelio.destroy', $order))
        ->assertRedirect(route('jubelio.index'))
        ->assertSessionHas('error');

    expect(Jubelioorder::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('shows delete control on show page only when allowed', function () {
    User::factory()->create();
    $deleter = User::factory()->create();
    $deleter->givePermissionTo([Jubelio::getPermissions()['view'], Jubelio::getPermissions()['delete']]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'del-ui',
        'source' => 3,
        'invoice' => 'SP-UI-DELETE',
        'type' => 'SELL',
        'order_status' => 'READY TO SHIP',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($deleter)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertSee('data-testid="jubelio-delete-order"', false);

    Transaction::factory()->create(['invoice' => 'SP-UI-DELETE']);

    $this->actingAs($deleter)
        ->get(route('jubelio.show', $order))
        ->assertSuccessful()
        ->assertDontSee('data-testid="jubelio-delete-order"', false);
});
