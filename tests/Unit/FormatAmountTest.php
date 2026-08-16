<?php

test('format_amount shows up to two decimal places and trims trailing zeros', function () {
    expect(format_amount(1.55))->toBe('1,55')
        ->and(format_amount(2))->toBe('2')
        ->and(format_amount(2.5))->toBe('2,5')
        ->and(format_amount(2.50))->toBe('2,5')
        ->and(format_amount(1000.55))->toBe('1.000,55')
        ->and(format_amount(-99.75))->toBe('-99,75')
        ->and(format_amount(null))->toBe('0')
        ->and(\App\Support\AmountFormatter::format(1.55))->toBe('1,55');
});
