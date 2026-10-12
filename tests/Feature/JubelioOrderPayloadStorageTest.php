<?php

use App\Models\Jubelioorder;
use Illuminate\Support\Facades\DB;

it('persists payload column when saving a jubelio order', function () {
    $payload = ['return_no' => 'RET-GUARD-1', 'items' => []];

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'ret-guard-1',
        'source' => 1,
        'invoice' => 'RET-GUARD-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 0,
        'payload' => json_encode($payload),
    ]);

    expect(json_decode((string) $order->fresh()->payload, true))->toBe($payload);

    $updated = ['return_no' => 'RET-GUARD-1', 'items' => [['item_id' => 1]]];
    $order->update(['run_count' => 1, 'payload' => json_encode($updated)]);

    expect(json_decode((string) $order->fresh()->payload, true))->toBe($updated);
});

it('clears stored return payloads via app:clear-jubelio-order-payloads', function () {
    $id = DB::table('jubelioorders')->insertGetId([
        'jubelio_order_id' => 'ret-legacy-1',
        'source' => 1,
        'invoice' => 'RET-LEGACY-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 2,
        'payload' => json_encode(['return_no' => 'RET-LEGACY-1']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('app:clear-jubelio-order-payloads', ['--type' => 'RETURN'])
        ->assertSuccessful();

    expect(DB::table('jubelioorders')->where('id', $id)->value('payload'))->toBeNull();
});
