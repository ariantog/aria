<?php

use App\Models\MarketplaceWebhook;
use App\Models\MarketplaceWebhookEvent;
use App\Models\TikTokShop;
use App\Models\User;
use App\Services\PermissionGenerator;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate(TikTokShop::getPermissions()['view'], 'web');
    Permission::findOrCreate(TikTokShop::getPermissions()['reply'], 'web');
    app(PermissionGenerator::class)->generateForModule('MarketplaceWebhook');
});

it('redirects legacy tiktok webhook inbox to unified marketplace inbox', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(TikTokShop::getPermissions()['view']);

    $this->actingAs($user)
        ->get(route('tiktok-shop.webhooks.index', ['channel' => 'tiktok']))
        ->assertRedirect(route('marketplace.webhooks.index', ['channel' => 'tiktok']));
});

it('renders unified marketplace webhook inbox for user with tiktok shop view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(TikTokShop::getPermissions()['view']);

    MarketplaceWebhookEvent::query()->create([
        'channel' => 'tiktok',
        'idempotency_key' => 'tiktok:ui-test-'.uniqid(),
        'event_type' => '1',
        'shop_ref' => '7495540735365777507',
        'order_ref' => '576653688135258178',
        'order_status' => 'UNPAID',
        'signature_ok' => true,
        'payload' => [
            'type' => 1,
            'data' => ['order_id' => '576653688135258178', 'order_status' => 'UNPAID'],
        ],
        'received_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('marketplace.webhooks.index', ['channel' => 'tiktok']))
        ->assertOk()
        ->assertSee('Marketplace — Webhook Inbox', false)
        ->assertSee('data-testid="marketplace-webhooks-page"', false)
        ->assertSee('576653688135258178', false)
        ->assertSee('UNPAID', false);
});

it('forbids unified marketplace webhook inbox without view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.webhooks.index'))
        ->assertForbidden();
});

it('allows superadmin to view unified marketplace webhook inbox without explicit permission', function () {
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('marketplace.webhooks.index'))
        ->assertOk()
        ->assertSee('Webhook Inbox', false);
});