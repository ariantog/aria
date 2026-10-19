<?php

use App\Models\LarkOpsSettings;
use App\Models\Setting;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Lark\LarkRosterService;
use App\Services\Lark\LarkSettingsService;
use App\Services\PermissionGenerator;
use Database\Seeders\StaffRoleChecklistSeeder;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('LarkOpsSettings');
    app(PermissionGenerator::class)->generateForModule('User');
    $this->seed(StaffRoleChecklistSeeder::class);
});

function larkSettingsNonSuperadminUser(): User
{
    User::factory()->create();

    return User::factory()->create();
}

test('lark settings page requires view permission', function () {
    $user = larkSettingsNonSuperadminUser();
    $this->actingAs($user)
        ->get(route('lark-settings.index'))
        ->assertForbidden();
});

test('lark settings can be saved and read back', function () {
    $user = larkSettingsNonSuperadminUser();
    Permission::findOrCreate(LarkOpsSettings::getPermissions()['edit'], 'web');
    $user->givePermissionTo(LarkOpsSettings::getPermissions()['edit']);
    $user->givePermissionTo(LarkOpsSettings::getPermissions()['view']);

    $role = StaffRole::query()->where('slug', 'gudang')->firstOrFail();

    $this->actingAs($user)
        ->put(route('lark-settings.update'), [
            'default_cover_days' => 10,
            'best_seller_cover_days' => 21,
            'rearm_buffer_days' => 3,
            'mismatch_qty_delta' => 5,
            'error_backlog_threshold' => 40,
            'jubelio_order_url_template' => 'https://jub.example/{invoice}',
            'events' => [
                'jubelio.oversell.webhook' => ['mention' => 'roster', 'roles' => [$role->slug]],
            ],
        ])
        ->assertRedirect(route('lark-settings.index'));

    $service = app(LarkSettingsService::class);
    expect($service->lowCover()['default_cover_days'])->toBe(10);
    expect($service->jubelio()['error_backlog_threshold'])->toBe(40);
    expect($service->jubelioOrderUrlTemplate())->toBe('https://jub.example/{invoice}');
    expect($service->eventConfig('jubelio.oversell.webhook'))
        ->toMatchArray(['mention' => 'roster', 'roles' => ['gudang']]);

    expect(Setting::query()->where('slug', LarkSettingsService::SETTING_SLUG)->exists())->toBeTrue();
});

test('lark roster excludes users with notify disabled', function () {
    Config::set('lark.enabled', true);

    User::factory()->create();

    $role = StaffRole::query()->where('slug', 'penjualan_online')->firstOrFail();

    $included = User::factory()->create([
        'lark_open_id' => 'ou_included',
        'lark_notify_enabled' => true,
    ]);
    $included->staffRoles()->attach($role->id);

    $excluded = User::factory()->create([
        'lark_open_id' => 'ou_excluded',
        'lark_notify_enabled' => false,
    ]);
    $excluded->staffRoles()->attach($role->id);

    $openIds = app(LarkRosterService::class)->openIdsForRoles(['penjualan_online']);

    expect($openIds)->toContain('ou_included')->not->toContain('ou_excluded');
});
