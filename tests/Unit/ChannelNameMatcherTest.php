<?php

use App\Services\Reporting\ChannelNameMatcher;

it('matches marketplace ledger names to channel customers', function () {
    $matcher = new ChannelNameMatcher;

    expect($matcher->score('Biaya Shopee', 'Shopee - CRYSTAL Customer'))->toBeGreaterThan(0)
        ->and($matcher->score('Biaya TikTok', 'TikTok Shop'))->toBeGreaterThan(0)
        ->and($matcher->score('Biaya Tokopedia', 'Tokped Channel'))->toBeGreaterThan(0)
        ->and($matcher->score('Biaya Shopee', 'TikTok Channel'))->toBe(0);
});

it('matches toko ledgers to warehouse names without hitting unrelated channels', function () {
    $matcher = new ChannelNameMatcher;

    expect($matcher->matchingIds('Biaya Toko WTC', [
        10 => 'Gudang WTC',
        11 => 'Gudang Citos',
    ]))->toBe([10])
        ->and($matcher->score('Biaya Toko WTC', 'Shopee Channel'))->toBe(0);
});

it('allocates leftover cents to the last weighted channel', function () {
    $allocated = app(\App\Services\Reporting\ChannelPnlService::class)->allocateAmount(100, [
        1 => 75,
        2 => 25,
    ]);

    expect($allocated)->toBe([
        1 => 75.0,
        2 => 25.0,
    ]);

    $even = app(\App\Services\Reporting\ChannelPnlService::class)->allocateAmount(100, [
        1 => 0,
        2 => 0,
    ]);

    expect($even[1] + $even[2])->toBe(100.0);
});
