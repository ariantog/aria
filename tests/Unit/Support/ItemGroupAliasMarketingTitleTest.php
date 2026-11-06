<?php

use App\Support\ItemGroupAliasMarketingTitle;

it('extracts the segment before - or / and uppercases when it has no digits', function () {
    expect(ItemGroupAliasMarketingTitle::extract('STRAIGHT LOOSE PANTS - MISTY GREY'))->toBe('STRAIGHT LOOSE PANTS');
});

it('returns null when the first segment contains a digit', function () {
    expect(ItemGroupAliasMarketingTitle::extract('CX90032/01'))->toBeNull();
});

it('returns null when the alias has no hyphen or slash', function () {
    expect(ItemGroupAliasMarketingTitle::extract('PLAIN TITLE'))->toBeNull();
});
