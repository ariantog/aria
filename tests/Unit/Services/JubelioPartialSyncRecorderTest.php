<?php

use App\Services\Jubelio\JubelioPartialSyncRecorder;
use App\Services\Jubelio\JubelioStockSync;

it('formats readable partial sync note blocks', function () {
    $recorder = app(JubelioPartialSyncRecorder::class);

    $block = $recorder->formatNoteBlock(
        JubelioStockSync::SIDE_SENDER,
        'Gudang BSD',
        [
            ['code' => 'SKU-A', 'qty' => 1.0, 'item_id' => 10],
            ['code' => 'SKU-B', 'qty' => 2.5, 'item_id' => 11],
        ],
        3,
        '99001',
    );

    expect($block)
        ->toContain(JubelioPartialSyncRecorder::NOTE_MARKER)
        ->toContain('Side A (pengirim)')
        ->toContain('Gudang: Gudang BSD')
        ->toContain('Referensi Jubelio: 99001')
        ->toContain('Disinkronkan: 3 baris terhubung.')
        ->toContain('SKU-A × 1')
        ->toContain('SKU-B × 2.5');
});
