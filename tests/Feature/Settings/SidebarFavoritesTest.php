<?php

use App\Models\User;
use App\Models\UserPreference;
use App\Services\PermissionGenerator;
use App\Services\SidebarFavoriteService;
use App\Support\UserPreferenceRegistry;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    app(PermissionGenerator::class)->generateForModule('Jubelio');
    app(PermissionGenerator::class)->generateForModule('Transaction');

    $this->user = User::factory()->create();

    expect($this->user->is_superadmin)->toBeFalse();
});

test('user can save up to five favorite links', function () {
    $this->user->givePermissionTo(['jubelio-view', 'transactions-type-buy']);

    $this->actingAs($this->user)
        ->put(route('favorites.update'), [
            'favorites' => [
                'jubelio-get-orders',
                'transactions-buy',
            ],
        ])
        ->assertRedirect(route('favorites.edit'))
        ->assertSessionHas('success');

    expect(app(SidebarFavoriteService::class)->favoriteKeys($this->user))
        ->toBe(['jubelio-get-orders', 'transactions-buy']);

    expect(UserPreference::query()
        ->where('user_id', $this->user->id)
        ->where('slug', UserPreferenceRegistry::FAVORITES_SLUG)
        ->value('value'))
        ->toBe(['jubelio-get-orders', 'transactions-buy']);
});

test('favorites settings page only lists permission-gated pages', function () {
    $this->user->givePermissionTo('jubelio-view');

    $available = collect(app(SidebarFavoriteService::class)->availableLinks($this->user))
        ->pluck('label')
        ->all();

    expect($available)->toContain('Get Orders')
        ->and($available)->not->toContain('Buy');

    $this->actingAs($this->user)
        ->get(route('favorites.edit'))
        ->assertOk()
        ->assertSee('Favorite links', false)
        ->assertSee('Get Orders', false)
        ->assertDontSee('value="transactions-buy"', false);
});

test('sidebar shows favorites dropdown above transactions', function () {
    $this->user->givePermissionTo(['jubelio-view', 'transactions-type-buy']);

    app(SidebarFavoriteService::class)->updateFavoriteKeys($this->user, [
        'jubelio-get-orders',
        'transactions-buy',
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

    $html = $response->getContent();
    $favoritesPos = strpos($html, '>Favorites<');
    $transactionsPos = strpos($html, '>Transactions<');

    expect($favoritesPos)->not->toBeFalse()
        ->and($transactionsPos)->not->toBeFalse()
        ->and($favoritesPos)->toBeLessThan($transactionsPos)
        ->and($html)->toContain('Get Orders')
        ->and($html)->toContain('Buy');
});

test('sidebar hides favorite links the user no longer has permission for', function () {
    Permission::findOrCreate('jubelio-view', 'web');
    Permission::findOrCreate('transactions-type-buy', 'web');

    $this->user->givePermissionTo(['jubelio-view', 'transactions-type-buy']);

    app(SidebarFavoriteService::class)->updateFavoriteKeys($this->user, [
        'jubelio-get-orders',
        'transactions-buy',
    ]);

    $this->user->syncPermissions(['jubelio-view']);

    $resolved = app(SidebarFavoriteService::class)->resolvedFavorites($this->user);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]['label'])->toBe('Get Orders');

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get Orders', false)
        ->assertDontSee('value="transactions-buy"', false);
});

test('user cannot save more than five favorites', function () {
    $this->user->givePermissionTo('jubelio-view');

    $this->actingAs($this->user)
        ->put(route('favorites.update'), [
            'favorites' => [
                'jubelio-get-orders',
                'jubelio-orders',
                'jubelio-cancellations',
                'jubelio-cek-order',
                'jubelio-koneksi',
                'jubelio-stock-sync',
            ],
        ])
        ->assertSessionHasErrors('favorites');
});

test('user cannot save favorites they do not have permission for', function () {
    $this->actingAs($this->user)
        ->put(route('favorites.update'), [
            'favorites' => ['jubelio-get-orders'],
        ])
        ->assertRedirect(route('favorites.edit'))
        ->assertSessionHas('error');
});

test('user cannot save unknown favorite keys', function () {
    $this->user->givePermissionTo('jubelio-view');

    $this->actingAs($this->user)
        ->put(route('favorites.update'), [
            'favorites' => ['not-a-real-link'],
        ])
        ->assertRedirect(route('favorites.edit'))
        ->assertSessionHas('error');
});

test('any authenticated user can access favorites settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('favorites.edit'))
        ->assertOk()
        ->assertSee('Save favorites', false);
});
