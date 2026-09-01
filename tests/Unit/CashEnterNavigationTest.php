<?php

it('collapses Android Chrome duplicate Enter on cash fields', function () {
    $script = __DIR__.'/cashEnterNavigation.test.cjs';
    $cmd = 'node --test '.escapeshellarg($script);
    exec($cmd.' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
});

it('keeps desktop Chrome sequential Enter navigation', function () {
    $script = __DIR__.'/cashEnterNavigation.chrome.cjs';
    $cmd = 'node '.escapeshellarg($script);
    exec($cmd.' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
    expect(implode("\n", $output))->toContain('"passed": true');
});
