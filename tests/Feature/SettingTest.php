<?php

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingRegistry;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();

    foreach (Setting::getPermissions() as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $this->seed(SettingSeeder::class);
});

test('unauthorized user cannot view settings', function () {
    $this->actingAs($this->user)
        ->get(route('system-settings.index'))
        ->assertStatus(403);
});

test('authorized user can view settings', function () {
    $this->user->givePermissionTo(Setting::getPermissions()['view']);

    $this->actingAs($this->user)
        ->get(route('system-settings.index'))
        ->assertStatus(200)
        ->assertSee('PPN Rate', false)
        ->assertDontSee('start_time', false);
});

test('settings index only shows managed settings', function () {
    Setting::create([
        'group' => 'General',
        'name' => 'Legacy Start Time',
        'slug' => 'start_time',
        'value' => '08:00',
    ]);

    $this->user->givePermissionTo(Setting::getPermissions()['view']);

    $this->actingAs($this->user)
        ->get(route('system-settings.index'))
        ->assertOk()
        ->assertDontSee('Legacy Start Time', false)
        ->assertDontSee('start_time', false);
});

test('authorized user can view create setting page', function () {
    $this->user->givePermissionTo(Setting::getPermissions()['create']);

    $this->actingAs($this->user)
        ->get(route('system-settings.create'))
        ->assertStatus(200);
});

test('authorized user can view invoice logo settings', function () {
    $this->user->givePermissionTo(Setting::getPermissions()['edit']);

    $this->actingAs($this->user)
        ->get(route('invoice-settings.edit'))
        ->assertOk()
        ->assertSee('Invoice Logo', false)
        ->assertDontSee('Default Address', false);
});

test('settings lookup returns suppliers and warehouses', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Beta Supplier']);

    $this->user->givePermissionTo(Setting::getPermissions()['view']);

    $this->actingAs($this->user)
        ->getJson(route('system-settings.lookup', ['type' => 'supplier', 'search' => 'Beta']))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $supplier->id, 'name' => 'Beta Supplier']);
});

test('authorized user can update restock warehouse ids from system settings', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Display WH']);
    $setting = Setting::where('slug', 'restock.default_warehouse_ids')->firstOrFail();

    $this->user->givePermissionTo(Setting::getPermissions()['edit']);

    $this->actingAs($this->user)
        ->put(route('system-settings.update', $setting->id), [
            'group' => $setting->group,
            'name' => $setting->name,
            'warehouse_ids' => [$warehouse->id],
        ])
        ->assertRedirect(route('system-settings.index'));

    expect(Setting::getValue('restock.default_warehouse_ids'))->toBe([$warehouse->id]);
});

test('authorized user can update produksi default warehouse via autocomplete value', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Prod WH']);
    $setting = Setting::where('slug', 'produksi.default_warehouse_id')->firstOrFail();

    $this->user->givePermissionTo(Setting::getPermissions()['edit']);

    $this->actingAs($this->user)
        ->put(route('system-settings.update', $setting->id), [
            'group' => $setting->group,
            'name' => $setting->name,
            'value' => $warehouse->id,
        ])
        ->assertRedirect(route('system-settings.index'));

    expect(Setting::getValue('produksi.default_warehouse_id'))->toBe($warehouse->id);
});

test('managed setting slugs are unique in registry', function () {
    $slugs = SettingRegistry::slugs();

    expect(count($slugs))->toBe(count(array_unique($slugs)));
});

test('settings cleanup command removes duplicate slug rows', function () {
    Setting::query()->where('slug', 'start_time')->delete();

    Setting::create([
        'group' => 'General',
        'name' => 'Start Time Old',
        'slug' => 'start_time',
        'value' => '08:00',
    ]);

    $this->artisan('settings:cleanup')
        ->assertSuccessful();

    expect(Setting::where('slug', 'start_time')->count())->toBe(0);
    expect(Setting::where('slug', 'produksi.default_warehouse_id')->count())->toBe(1);
});

test('getValue reads the newest row when duplicate slug rows exist', function () {
    if (Schema::hasColumn('settings', 'slug')) {
        Schema::table('settings', function ($table) {
            $table->dropUnique(['slug']);
        });
    }

    Setting::query()->where('slug', 'produksi.default_warehouse_id')->delete();

    Setting::create([
        'group' => 'Produksi',
        'name' => 'Default Gudang (Warehouse) Old',
        'slug' => 'produksi.default_warehouse_id',
        'value' => 1,
    ]);
    Setting::create([
        'group' => 'Produksi',
        'name' => 'Default Gudang (Warehouse)',
        'slug' => 'produksi.default_warehouse_id',
        'value' => 99,
    ]);

    expect(Setting::getValue('produksi.default_warehouse_id'))->toBe(99);
});
