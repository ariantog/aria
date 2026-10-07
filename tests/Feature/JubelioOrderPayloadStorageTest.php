<?php

use App\Models\Jubelioorder;
use Illuminate\Support\Facades\DB;

it('does not persist payload column when saving a return jubelio order', function () {
    $order = Jubelioorder::create([
        'jubelio_order_id' => 'ret-guard-1',
        'source' => 1,
        'invoice' => 'RET-GUARD-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 0,
        'payload' => json_encode(['return_no' => 'RET-GUARD-1', 'items' => []]),
    ]);

    expect($order->fresh()->payload)->toBeNull();

    $order->update(['run_count' => 1, 'payload' => json_encode(['return_no' => 'RET-GUARD-1'])]);

    expect($order->fresh()->payload)->toBeNull();
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
