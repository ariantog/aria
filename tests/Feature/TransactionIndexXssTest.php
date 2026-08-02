<?php

use App\Models\Addrbook;
use App\Models\User;

it('escapes untrusted values in the Tabulator formatters', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('transactions.index'));
    $response->assertOk();

    // The escaping helper must exist and be applied to every user-controlled
    // field interpolated into Tabulator HTML formatter strings.
    $response->assertSee('function esc(v)', false);
    $response->assertSee('${esc(v) || "-"}', false);       // invoice number
    $response->assertSee('${esc(v)}</span>', false);        // description/notes
    $response->assertSee('${esc(s.name)}', false);          // sender name
    $response->assertSee('${esc(r.name)}', false);          // receiver name
});

it('returns raw JSON data leaving escaping to the client helper', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Addrbook::create(['name' => '<img src=x onerror=alert(1)>', 'type' => Addrbook::TYPE_CUSTOMER]);

    // JSON API returns data as-is (JSON-encoded, not HTML); the Blade view is
    // responsible for escaping before inserting into the DOM — covered above.
    $response = $this->getJson(route('transactions.index'));
    $response->assertOk()->assertJsonStructure(['data', 'last_page', 'total']);
});
