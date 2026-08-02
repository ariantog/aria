<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;

/**
 * The transactions index is a plain server-rendered table, so every user-controlled value
 * goes through Blade's `{{ }}` escaping. These tests pin that behaviour down: a stored
 * payload in a contact name, invoice number or description must never reach the browser
 * as live markup.
 */
it('escapes stored payloads in contact names, invoice numbers and descriptions', function () {
    $user = User::factory()->create();

    $payload = '<img src=x onerror=alert(1)>';

    $sender = Addrbook::factory()->supplier()->create(['name' => $payload]);
    $receiver = Addrbook::factory()->warehouse()->create(['name' => 'Safe Warehouse']);

    Transaction::factory()->create([
        'type'           => Transaction::TYPE_BUY,
        'invoice_number' => $payload,
        'description'    => $payload,
        'sender_type'    => (string) Addrbook::TYPE_SUPPLIER,
        'sender_id'      => $sender->id,
        'receiver_type'  => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id'    => $receiver->id,
        'user_id'        => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('transactions.index'));
    $response->assertOk();

    $html = $response->getContent();

    // The raw tag must never appear; only its escaped form.
    expect($html)->not->toContain('<img src=x onerror=alert(1)>');
    expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('escapes a payload echoed back through the filter inputs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('transactions.index', [
        'invoice_number' => '"><script>alert(1)</script>',
    ]));

    $response->assertOk();

    expect($response->getContent())->not->toContain('<script>alert(1)</script>');
});
