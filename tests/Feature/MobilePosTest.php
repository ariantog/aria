<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WarehouseItem;
use App\Support\UserPreferenceRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
});

test('mobile me includes pos preferences', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    UserPreference::query()->create([
        'user_id' => $user->id,
        'slug' => UserPreferenceRegistry::MOBILE_POS_SENDER_ID_SLUG,
        'value' => (string) $warehouse->id,
    ]);
    UserPreference::query()->create([
        'user_id' => $user->id,
        'slug' => UserPreferenceRegistry::MOBILE_POS_RECEIVER_ID_SLUG,
        'value' => (string) $customer->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/v1/me')
        ->assertOk()
        ->assertJsonPath('preferences.pos.sender_id', $warehouse->id)
        ->assertJsonPath('preferences.pos.receiver_id', $customer->id);
});

test('mobile patch pos defaults persists warehouse customer and whatsapp', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/mobile/v1/preferences/pos-defaults', [
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
            'whatsapp_phone' => '081234567890',
        ])
        ->assertOk()
        ->assertJsonPath('pos.sender_id', $warehouse->id)
        ->assertJsonPath('pos.whatsapp_phone', '081234567890');

    expect(UserPreference::query()
        ->where('user_id', $user->id)
        ->where('slug', UserPreferenceRegistry::MOBILE_POS_WHATSAPP_PHONE_SLUG)
        ->value('value'))->toBe('081234567890');
});

test('mobile pos sell returns receipt whatsapp and jubelio reminder', function () {
    Config::set('services.jubelio.active', true);

    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['price' => 25_000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 50,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 99,
        'jubelio_store_name' => 'POS Store',
        'jubelio_location_id' => 8,
        'jubelio_location_name' => 'Online',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/transactions/item', [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'pos' => true,
        'whatsapp_phone' => '08199887766',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 25_000,
                'discount' => 0,
            ],
        ],
    ], ['X-Idempotency-Key' => 'mobile-pos-sell-'.uniqid()]);

    $response->assertCreated()
        ->assertJsonPath('pos.jubelio_reminder.show', true)
        ->assertJsonStructure(['pos' => ['receipt_url', 'whatsapp' => ['url', 'phone']]]);

    $id = (int) $response->json('id');
    expect($response->json('pos.whatsapp.url'))->toContain('wa.me/08199887766');

    $receipt = $this->actingAs($user, 'sanctum')
        ->get('/api/mobile/v1/transactions/'.$id.'/receipt');

    $receipt->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('mobile pos sell remember defaults writes preferences', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['price' => 1000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => 10,
    ]);

    $this->actingAs($user, 'sanctum')->postJson('/api/mobile/v1/transactions/item', [
        'date' => now()->toDateString(),
        'type' => 'sell',
        'pos' => true,
        'remember_defaults' => true,
        'whatsapp_phone' => '08987654321',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 1000,
                'discount' => 0,
            ],
        ],
    ], ['X-Idempotency-Key' => 'mobile-pos-remember-'.uniqid()])
        ->assertCreated();

    expect((int) UserPreference::query()
        ->where('user_id', $user->id)
        ->where('slug', UserPreferenceRegistry::MOBILE_POS_SENDER_ID_SLUG)
        ->value('value'))->toBe($warehouse->id);
});

afterEach(function () {
    $path = rtrim((string) config('core-nation.invoice_path'), DIRECTORY_SEPARATOR);
    if (File::isDirectory($path)) {
        foreach (glob($path.DIRECTORY_SEPARATOR.'receipt_*.pdf') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($path.DIRECTORY_SEPARATOR.'invoice_*.pdf') ?: [] as $file) {
            @unlink($file);
        }
    }
});
