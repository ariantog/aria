<?php

use App\Models\Addrbook;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create permissions if not exist
    $permissions = Addrbook::getPermissions();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $this->user->givePermissionTo(array_values($permissions));
});

test('all contacts index is no longer available', function () {
    $this->actingAs($this->user)
        ->get('/addrbook')
        ->assertNotFound();
});

test('other type is excluded from navigable addrbook types', function () {
    $slugs = array_column(Addrbook::getTypes(), 'slug');

    expect($slugs)->not->toContain('other');
});

test('can view customer list through type-specific route', function () {
    $this->actingAs($this->user)
        ->get(route('addrbook.type.index', 'customer'))
        ->assertOk();
});

test('can view customer create through type-specific route', function () {
    $this->actingAs($this->user)
        ->get(route('addrbook.type.create', 'customer'))
        ->assertStatus(200)
        ->assertSee('Basic Information');
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

    $response->assertRedirect(Addrbook::typeIndexRoute(Addrbook::TYPE_CUSTOMER));

    $this->assertDatabaseHas('customers', [
        'name' => 'New Customer',
        'phone' => '08123456789',
        'is_online' => 1,
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    // Get the created addrbook to check stats
    $addrbook = Addrbook::where('name', 'New Customer')->first();
    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $addrbook->id,
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

    $response->assertRedirect(Addrbook::typeIndexRoute(Addrbook::TYPE_SUPPLIER));

    $this->assertDatabaseHas('customers', [
        'id' => $addrbook->id,
        'name' => 'Updated Name',
        'ppn' => 1,
        'type' => Addrbook::TYPE_SUPPLIER,
    ]);
});

test('can update warehouse with empty optional fields', function () {
    $addrbook = Addrbook::create([
        'name' => 'Warehouse Old',
        'type' => Addrbook::TYPE_WAREHOUSE,
        'email' => 'old@example.com',
        'phone' => '08123456789',
    ]);

    $this->actingAs($this->user)
        ->put(route('addrbook.update', $addrbook), [
            'name' => 'Warehouse Updated',
            'type' => Addrbook::TYPE_WAREHOUSE,
            'email' => '',
            'phone' => '082313651678',
            'description' => 'CORENATION WTC MANGGA DUA LT2 BLOK B',
            'address' => '',
        ])
        ->assertRedirect(Addrbook::typeIndexRoute(Addrbook::TYPE_WAREHOUSE));

    $addrbook->refresh();

    expect($addrbook->name)->toBe('Warehouse Updated')
        ->and($addrbook->phone)->toBe('082313651678')
        ->and($addrbook->description)->toBe('CORENATION WTC MANGGA DUA LT2 BLOK B');
});

test('can delete addrbook', function () {
    $addrbook = Addrbook::create([
        'name' => 'To Delete',
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('addrbook.destroy', $addrbook));

    $response->assertRedirect(Addrbook::typeIndexRoute(Addrbook::TYPE_CUSTOMER));

    $this->assertSoftDeleted('customers', ['id' => $addrbook->id]);
    $this->assertDatabaseHas('customers', ['id' => $addrbook->id, 'name' => 'To Delete']);
    expect(Addrbook::find($addrbook->id))->toBeNull();
    expect(Addrbook::withTrashed()->find($addrbook->id))->not->toBeNull();
});

test('addrbook list trash button soft deletes via fetch delete', function () {
    $addrbook = Addrbook::create([
        'name' => 'Trash Button Target',
        'type' => Addrbook::TYPE_CUSTOMER,
    ]);

    $this->actingAs($this->user)
        ->post(route('addrbook.destroy', $addrbook), ['_method' => 'DELETE'], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertRedirect(Addrbook::typeIndexRoute(Addrbook::TYPE_CUSTOMER));

    $this->assertSoftDeleted('customers', ['id' => $addrbook->id]);
    $this->assertDatabaseHas('customers', ['id' => $addrbook->id, 'name' => 'Trash Button Target']);

    $this->actingAs($this->user)
        ->get(route('addrbook.type.index', 'customer').'?trashed=only')
        ->assertOk()
        ->assertSee('Trash Button Target', false)
        ->assertSee('Deleted', false);
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
        ->assertSee('Show Test');
});

test('can view addrbook edit through type-specific route', function () {
    $addrbook = Addrbook::create([
        'name' => 'Edit Test',
        'type' => Addrbook::TYPE_SUPPLIER,
    ]);

    $this->actingAs($this->user)
        ->get("/supplier/{$addrbook->id}/edit")
        ->assertStatus(200)
        ->assertSee('Edit Test');
});

test('can view warehouse edit through type-specific route', function () {
    $addrbook = Addrbook::create([
        'name' => 'Warehouse Edit Test',
        'type' => Addrbook::TYPE_WAREHOUSE,
    ]);

    $this->actingAs($this->user)
        ->get("/warehouse/{$addrbook->id}/edit")
        ->assertStatus(200)
        ->assertSee('Warehouse Edit Test');
});

test('persists warehouse arrangement source warehouses on update', function () {
    $destination = Addrbook::create([
        'name' => 'Destination WH',
        'type' => Addrbook::TYPE_WAREHOUSE,
        'arrangement_enabled' => true,
    ]);
    $source = Addrbook::create([
        'name' => 'Source WH',
        'type' => Addrbook::TYPE_WAREHOUSE,
    ]);

    $this->actingAs($this->user)
        ->put(route('addrbook.update', $destination), [
            'name' => 'Destination WH',
            'type' => Addrbook::TYPE_WAREHOUSE,
            'arrangement_enabled' => true,
            'arrangement_source_ids' => [$source->id],
            'ppn' => false,
            'is_online' => false,
        ])
        ->assertRedirect(Addrbook::typeIndexRoute(Addrbook::TYPE_WAREHOUSE));

    expect($destination->fresh()->arrangementSources->pluck('id')->all())->toBe([$source->id]);

    $this->actingAs($this->user)
        ->get("/warehouse/{$destination->id}/edit")
        ->assertOk()
        ->assertSee('Source WH', false);
});

test('bank list is sorted by name', function () {
    Addrbook::factory()->create(['name' => 'Zebra Bank', 'type' => Addrbook::TYPE_BANK]);
    Addrbook::factory()->create(['name' => 'Alpha Bank', 'type' => Addrbook::TYPE_BANK]);

    $this->actingAs($this->user)
        ->get(route('addrbook.type.index', 'bank'))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Bank', 'Zebra Bank'], false);
});
