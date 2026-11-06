<?php

use App\Models\User;
use App\Services\TikTokShop\TikTokShopMockDataStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('tiktok_shop.driver', 'mock');
    Config::set('tiktok_shop.mock_api_token', 'test-mock-token');
    Cache::flush();

    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

it('renders the tiktok shop chat inbox with demo conversations', function () {
    $this->actingAs($this->user)
        ->get(route('tiktok-shop.chats.index'))
        ->assertOk()
        ->assertSee('TikTok Shop — Customer Chat', false)
        ->assertSee('data-testid="tiktok-shop-chats-page"', false)
        ->assertSee('Sari Wijaya', false);
});

it('sends a seller reply through the mock client without database tables', function () {
    $this->actingAs($this->user)
        ->get(route('tiktok-shop.chats.index'))
        ->assertOk();

    $conversationId = array_key_first(app(TikTokShopMockDataStore::class)->state()['conversations']);
    expect($conversationId)->not->toBeEmpty();

    $this->actingAs($this->user)
        ->post(route('tiktok-shop.chats.reply', $conversationId), [
            'content' => 'Terima kasih kak, pesanan segera kami proses.',
        ])
        ->assertRedirect();

    $messages = app(TikTokShopMockDataStore::class)->state()['messages'][$conversationId] ?? [];
    $contents = array_column($messages, 'content');

    expect($contents)->toContain('Terima kasih kak, pesanan segera kami proses.');
});

it('exposes mock open api conversations with token header', function () {
    $this->actingAs($this->user)->get(route('tiktok-shop.chats.index'));

    $response = $this->getJson(route('tiktok-shop.mock.conversations'), [
        'x-tts-mock-token' => 'test-mock-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonStructure(['data' => ['conversations', 'total_count']]);

    expect($response->json('data.conversations'))->not->toBeEmpty();
});

it('rejects mock open api without token', function () {
    $this->getJson(route('tiktok-shop.mock.conversations'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 40101);
});

it('posts seller message via mock open api', function () {
    $this->actingAs($this->user)
        ->get(route('tiktok-shop.chats.index'))
        ->assertOk();

    $conversationId = array_key_first(app(TikTokShopMockDataStore::class)->state()['conversations']);
    expect($conversationId)->not->toBeEmpty();

    $this->postJson(
        route('tiktok-shop.mock.send', $conversationId),
        ['content' => 'Halo dari mock API'],
        ['x-tts-mock-token' => 'test-mock-token'],
    )
        ->assertOk()
        ->assertJsonPath('data.message.content', 'Halo dari mock API');
});
