<?php

use App\Models\Addrbook;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LocationAccessService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->locationA = Location::create(['name' => 'Location A']);
    $this->locationB = Location::create(['name' => 'Location B']);

    $this->addrbookA = Addrbook::factory()->create(['name' => 'Contact A']);
    $this->addrbookB = Addrbook::factory()->create(['name' => 'Contact B']);

    $this->addrbookA->locations()->attach($this->locationA->id);
    $this->addrbookB->locations()->attach($this->locationB->id);

    $this->user = User::factory()->create(['location_id' => $this->locationA->id]);

    Permission::firstOrCreate(['name' => 'addrbook-list']);
    Permission::firstOrCreate(['name' => 'transactions-list']);
    $this->user->givePermissionTo(['addrbook-list', 'transactions-list']);
});

it('filters addrbooks by user location', function () {
    $visible = Addrbook::query()->visibleToUser($this->user)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id)
        ->not->toContain($this->addrbookB->id);
});

it('allows superadmin to see all addrbooks', function () {
    $superadmin = User::find(1);

    $visible = Addrbook::query()->visibleToUser($superadmin)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id, $this->addrbookB->id);
});

it('filters transactions by participant location', function () {
    $visibleTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookA->id,
        'receiver_id' => $this->addrbookB->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'sender_id' => $this->addrbookB->id,
        'receiver_id' => $this->addrbookB->id,
    ]);

    $ids = Transaction::query()->visibleToUser($this->user)->pluck('id');

    expect($ids)->toContain($visibleTx->id)
        ->not->toContain($hiddenTx->id);
});

it('reports location access through service helper', function () {
    $service = app(LocationAccessService::class);

    expect($service->canAccessAddrbook($this->user, $this->addrbookA))->toBeTrue()
        ->and($service->canAccessAddrbook($this->user, $this->addrbookB))->toBeFalse();
});

it('treats users without location as unrestricted', function () {
    $unrestricted = User::factory()->create(['location_id' => null]);

    $visible = Addrbook::query()->visibleToUser($unrestricted)->pluck('id');

    expect($visible)->toContain($this->addrbookA->id, $this->addrbookB->id);
});
