<?php

use App\Exceptions\InsufficientWarehouseStockException;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    Route::middleware('web')->post('/__stock-gate-form', function () {
        throw new InsufficientWarehouseStockException('SKU-X', 1, 4, 9);
    });
});

it('renders ajax stock-gate failures as laravel validation json', function () {
    $this->postJson('/__stock-gate-form')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items'])
        ->assertJsonPath('errors.items.0', 'SKU-X cuma ada 1, mau diambil 4')
        ->assertJsonPath('message', 'SKU-X cuma ada 1, mau diambil 4');
});

it('redirects html form posts back with field errors and a flash message', function () {
    $this->from('/transactions/create/sell')
        ->post('/__stock-gate-form')
        ->assertRedirect('/transactions/create/sell')
        ->assertSessionHasErrors(['items'])
        ->assertSessionHas('error', 'SKU-X cuma ada 1, mau diambil 4');
});
