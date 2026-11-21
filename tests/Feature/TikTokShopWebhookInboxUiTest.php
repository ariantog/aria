<?php

use App\Models\TikTokShop;
use App\Models\TikTokShopWebhookInbox;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate(TikTokShop::getPermissions()['view'], 'web');
    Permission::findOrCreate(TikTokShop::getPermissions()['reply'], 'web');
});

it('renders tiktok shop webhook inbox for user with view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(TikTokShop::getPermissions()['view']);

    TikTokShopWebhookInbox::query()->create([
        'tts_notification_id' => 'ui-test-'.uniqid(),
        'event_type' => 1,
        'shop_id' => '7495540735365777507',
        'order_id' => '576653688135258178',
        'order_status' => 'UNPAID',
        'signature_ok' => true,
        'payload' => [
            'type' => 1,
            'tts_notification_id' => 'ui-test',
            'data' => ['order_id' => '576653688135258178', 'order_status' => 'UNPAID'],
        ],
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('tiktok-shop.webhooks.index'))
        ->assertOk()
        ->assertSee('TikTok Shop — Webhook Inbox', false)
        ->assertSee('data-testid="tiktok-shop-webhooks-page"', false)
        ->assertSee('576653688135258178', false)
        ->assertSee('UNPAID', false)
        ->assertSee('data-testid="tiktok-webhooks-table"', false);
});

it('forbids tiktok shop webhook inbox without view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tiktok-shop.webhooks.index'))
        ->assertForbidden();
});

it('allows superadmin to view webhook inbox without explicit permission', function () {
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('tiktok-shop.webhooks.index'))
        ->assertOk()
        ->assertSee('Webhook Inbox', false);
});