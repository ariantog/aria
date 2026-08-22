<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Cache::flush();

    Route::middleware(['web', 'prevent.duplicate'])
        ->post('/_test/idempotent', function () {
            return response()->json(['hits' => Cache::increment('test_idempotency_hits')]);
        });
});

it('replays cached responses for duplicate idempotency keys', function () {
    $user = User::factory()->create();
    $key = 'idem-'.str_repeat('a', 20);

    $first = $this->actingAs($user)->postJson('/_test/idempotent', [], [
        'X-Idempotency-Key' => $key,
    ]);
    $first->assertOk()->assertJson(['hits' => 1]);

    $second = $this->actingAs($user)->postJson('/_test/idempotent', [], [
        'X-Idempotency-Key' => $key,
    ]);
    $second->assertOk()->assertJson(['hits' => 1]);
});

it('accepts idempotency keys from a hidden form field', function () {
    $user = User::factory()->create();
    $key = 'form-'.str_repeat('b', 20);

    $first = $this->actingAs($user)->post('/_test/idempotent', [
        '_idempotency_key' => $key,
    ]);
    $first->assertOk();

    expect(Cache::get('test_idempotency_hits'))->toBe(1);

    $second = $this->actingAs($user)->post('/_test/idempotent', [
        '_idempotency_key' => $key,
    ]);
    $second->assertOk();

    expect(Cache::get('test_idempotency_hits'))->toBe(1);
});

it('passes through requests without an idempotency key', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/_test/idempotent')->assertOk()->assertJson(['hits' => 1]);
    $this->actingAs($user)->postJson('/_test/idempotent')->assertOk()->assertJson(['hits' => 2]);
});

it('renders transaction forms with submit guard helpers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/transactions/cash-in')
        ->assertOk()
        ->assertSee('beginSubmit', false)
        ->assertSee('formSubmitGuard', false);

    $this->actingAs($user)
        ->get('/transactions/sell/create')
        ->assertOk()
        ->assertSee('beginSubmit', false);
});
