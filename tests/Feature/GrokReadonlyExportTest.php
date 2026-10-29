<?php

use App\Models\Item;
use App\Models\ItemParentPrice;
use App\Models\ReadonlyExport;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemPricing;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    foreach (ReadonlyExport::getPermissions() as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
});

function createGrokUser(): User
{
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $user->givePermissionTo(ReadonlyExport::getPermissions()['export']);

    return $user;
}

function nonSuperadminUser(): User
{
    $user = User::factory()->create(['active' => true]);

    return $user->id === User::SUPERADMIN_ID
        ? User::factory()->create(['active' => true])
        : $user;
}

function snapshotWriteFingerprint(): array
{
    return [
        'items_max_updated' => Item::query()->max('updated_at'),
        'items_count' => Item::count(),
        'warehouse_item_sum' => (float) WarehouseItem::query()->sum('quantity'),
    ];
}

it('registers readonly export routes as GET only', function (): void {
    $names = [
        'api.readonly.items',
        'api.readonly.items.cost-history',
        'api.readonly.stock',
        'exports.items.csv',
    ];

    foreach ($names as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull("Missing route {$name}");
        expect($route->methods())->toContain('GET')
            ->and($route->methods())->not->toContain('POST')
            ->and($route->methods())->not->toContain('PUT')
            ->and($route->methods())->not->toContain('PATCH')
            ->and($route->methods())->not->toContain('DELETE');
    }
});

it('returns hierarchy-aware base cost from parent group when SKU cost is unset', function (): void {
    $user = createGrokUser();
    Sanctum::actingAs($user, ['read-only']);

    $item = Item::factory()->create([
        'cost' => 0,
        'price' => 19_000,
        'code' => 'KNEESTRAP-01-BLACK-S',
    ]);
    $item->load('group', 'tags');
    $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($item);

    ItemParentPrice::query()->updateOrCreate(
        ['parent_key' => $parentKey],
        ['cost' => 1000, 'price' => 0, 'reseller_price' => 0, 'cost_cnh' => 0],
    );

    ItemPricing::flushRequestCache();

    $response = $this->getJson('/api/readonly/items?search='.urlencode($item->code));
    $response->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('id', $item->id);
    expect($row)->not->toBeNull()
        ->and((float) $row['base_cost'])->toBe(1000.0)
        ->and($row['group_code'])->toBe($parentKey)
        ->and($row['cost_flag'])->toBe('placeholder')
        ->and($row['name'])->not->toBe('');
});

it('does not mutate database when calling readonly export endpoints', function (): void {
    $user = createGrokUser();
    Sanctum::actingAs($user, ['read-only']);

    $item = Item::factory()->create();
    $before = snapshotWriteFingerprint();

    $this->getJson('/api/readonly/items?per_page=5')->assertSuccessful();
    $this->getJson('/api/readonly/stock?code='.urlencode($item->code))->assertSuccessful();
    $this->getJson('/api/readonly/items/'.$item->id.'/cost-history')->assertSuccessful();
    $this->actingAs($user)->get(route('exports.items.csv'))->assertSuccessful();

    $after = snapshotWriteFingerprint();

    expect($after)->toBe($before);
});

it('forbids grok export user from POST mutations', function (): void {
    $user = createGrokUser();
    $this->actingAs($user);

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post(route('permissions.generate'))
        ->assertForbidden();
    $this->get(route('items.edit', Item::factory()->create()))->assertForbidden();
});

it('returns 403 for readonly API without permission', function (): void {
    Sanctum::actingAs(nonSuperadminUser());

    $this->getJson('/api/readonly/items')->assertForbidden();
});
