<?php

use App\Models\Tag;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('syncs warna tag code to uppercase name on save', function () {
    $tag = Tag::create([
        'type' => Tag::TYPE_WARNA,
        'name' => 'blue',
        'code' => 'old-code',
        'item_type' => 0,
    ]);

    expect($tag->fresh())
        ->name->toBe('BLUE')
        ->code->toBe('BLUE');
});

it('normalizes warna attributes via helper', function () {
    $normalized = Tag::normalizeWarnaAttributes([
        'type' => Tag::TYPE_WARNA,
        'name' => 'pink',
        'code' => 'ignored',
    ]);

    expect($normalized)
        ->name->toBe('PINK')
        ->code->toBe('PINK');
});
