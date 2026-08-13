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
