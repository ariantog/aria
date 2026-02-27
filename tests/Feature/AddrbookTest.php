<?php

use App\Models\Addrbook;
use App\Models\User;
use Spatie\Permission\Models\Permission;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create permissions if not exist
    $permissions = Addrbook::getPermissions();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $this->user->givePermissionTo(array_values($permissions));
});

test('can view addrbook index', function () {
    $this->actingAs($this->user)
        ->get(route('addrbook.index'))
        ->assertStatus(200);
});

test('can create addrbook', function () {
    $response = $this->actingAs($this->user)
        ->post(route('addrbook.store'), [
            'name' => 'New Customer',
            'phone' => '08123456789',
            'initial_balance' => 1000,
            'is_online' => true,
            'ppn' => false,
            'type' => Addrbook::TYPE_CUSTOMER,
        ]);

    $response->assertRedirect(route('addrbook.index'));

    $this->assertDatabaseHas('addrbooks', [
        'name' => 'New Customer',
        'phone' => '08123456789',
        'is_online' => 1,
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    // Get the created addrbook to check stats
    $addrbook = Addrbook::where('name', 'New Customer')->first();
    $this->assertDatabaseHas('addrbook_stats', [
        'addrbook_id' => $addrbook->id,
        'balance' => 1000,
    ]);
});

test('can update addrbook', function () {
    $addrbook = Addrbook::create([
        'name' => 'Old Name',
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('addrbook.update', $addrbook), [
            'name' => 'Updated Name',
            'is_online' => false,
            'ppn' => true,
            'type' => Addrbook::TYPE_SUPPLIER,
        ]);

    $response->assertRedirect(route('addrbook.index'));

    $this->assertDatabaseHas('addrbooks', [
        'id' => $addrbook->id,
        'name' => 'Updated Name',
        'ppn' => 1,
        'type' => Addrbook::TYPE_SUPPLIER,
    ]);
});

test('can delete addrbook', function () {
    $addrbook = Addrbook::create([
        'name' => 'To Delete',
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('addrbook.destroy', $addrbook));

    $response->assertRedirect(route('addrbook.index'));

    $this->assertSoftDeleted('addrbooks', ['id' => $addrbook->id]);
});

test('addrbook has type_slug attribute', function () {
    $addrbook = Addrbook::create([
        'name' => 'Test Customer',
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    expect($addrbook->type_slug)->toBe('customer');

    $addrbook->type = Addrbook::TYPE_SUPPLIER;
    expect($addrbook->type_slug)->toBe('supplier');
});

test('can view addrbook through type-specific show route', function () {
    $addrbook = Addrbook::create([
        'name' => 'Show Test',
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    $this->actingAs($this->user)
        ->get("/customer/{$addrbook->id}")
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Addrbook/Show')
            ->has('addrbook', fn ($page) => $page
                ->where('id', $addrbook->id)
                ->where('name', 'Show Test')
                ->etc()
            )
        );
});

test('can view addrbook edit through type-specific route', function () {
    $addrbook = Addrbook::create([
        'name' => 'Edit Test',
        'type' => Addrbook::TYPE_SUPPLIER,
    ]);

    $this->actingAs($this->user)
        ->get("/supplier/{$addrbook->id}/edit")
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Addrbook/Edit')
            ->has('addrbook', fn ($page) => $page
                ->where('id', $addrbook->id)
                ->where('name', 'Edit Test')
                ->etc()
            )
        );
});
