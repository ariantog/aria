<?php

use App\Models\ItemParent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('new item parent rows get empty description defaults before insert', function () {
    $parent = new ItemParent(['parent_key' => '2:UNIT:PARENT']);
    $parent->save();
    $parent->refresh();

    expect(trim((string) $parent->description))->toBe('')
        ->and(trim((string) $parent->description2))->toBe('');
});
