<?php

use App\Support\LikeSearch;

it('turns whitespace into wildcards for contains patterns', function () {
    expect(LikeSearch::contains('foo bar'))->toBe('%foo%bar%')
        ->and(LikeSearch::contains('  foo   bar  '))->toBe('%foo%bar%');
});

it('turns whitespace into wildcards for prefix patterns', function () {
    expect(LikeSearch::prefix('ABC 123'))->toBe('ABC%123%');
});

it('escapes user supplied like metacharacters', function () {
    expect(LikeSearch::contains('100% off'))->toBe('%100\%%off%')
        ->and(LikeSearch::contains('a_b'))->toBe('%a\_b%');
});

it('treats user percent as like wildcards when enabled', function () {
    expect(LikeSearch::contains('cx00122%03', allowPercentWildcards: true))->toBe('%cx00122%03%')
        ->and(LikeSearch::contains('keyword1%keyword2', allowPercentWildcards: true))->toBe('%keyword1%keyword2%')
        ->and(LikeSearch::containsInsensitive('CX00122%03', allowPercentWildcards: true))->toBe('%cx00122%03%');
});

it('still escapes underscore when percent wildcards are enabled', function () {
    expect(LikeSearch::contains('a_b%c', allowPercentWildcards: true))->toBe('%a\_b%c%');
});

it('detects match-all like patterns', function () {
    expect(LikeSearch::isMatchAll('%'))->toBeTrue()
        ->and(LikeSearch::isMatchAll('%%'))->toBeTrue()
        ->and(LikeSearch::isMatchAll(LikeSearch::contains('%', allowPercentWildcards: true)))->toBeTrue()
        ->and(LikeSearch::isMatchAll('%cx00122%03%'))->toBeFalse();
});
