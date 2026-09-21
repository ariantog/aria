<?php

use App\Models\Tag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

function enforceProductionTagsNameUnique(): void
{
    Schema::table('tags', function (Blueprint $table) {
        $table->unique('name');
    });
}

it('normalizes warna tag name and defaults empty code from name on save', function () {
    $tag = Tag::create([
        'type' => Tag::TYPE_WARNA,
        'name' => 'blue',
        'code' => '',
        'item_type' => 0,
    ]);

    expect($tag->fresh())
        ->name->toBe('BLUE')
        ->code->toBe('BLUE');
});

it('preserves warna tag code when it differs from name', function () {
    $tag = Tag::create([
        'type' => Tag::TYPE_WARNA,
        'name' => 'GREYWHITE',
        'code' => 'GW',
        'item_type' => 0,
    ]);

    expect($tag->fresh())
        ->name->toBe('GREYWHITE')
        ->code->toBe('GW');

    $tag->update(['code' => 'BLACKWHITE']);

    expect($tag->fresh()->code)->toBe('BLACKWHITE');
});

it('normalizes warna attributes via helper', function () {
    $normalized = Tag::normalizeWarnaAttributes([
        'type' => Tag::TYPE_WARNA,
        'name' => 'pink',
        'code' => 'PK',
    ]);

    expect($normalized)
        ->name->toBe('PINK')
        ->code->toBe('PK');

    $defaulted = Tag::normalizeWarnaAttributes([
        'type' => Tag::TYPE_WARNA,
        'name' => 'pink',
        'code' => '',
    ]);

    expect($defaulted)
        ->name->toBe('PINK')
        ->code->toBe('PINK');
});

it('reuses an existing GREYWHITE warna tag when code differs', function () {
    $existing = Tag::withoutEvents(fn () => Tag::query()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => 'GW',
        'name' => 'GREYWHITE',
        'item_type' => 0,
    ]));

    $resolved = Tag::findOrCreateWarnaTag('GREYWHITE');

    expect($resolved->id)->toBe($existing->id)
        ->and(Tag::query()->whereRaw('UPPER(TRIM(name)) = ?', ['GREYWHITE'])->count())->toBe(1);
});

it('reuses GREYWHITE by name when a non-warna tag already owns that unique name', function () {
    $existing = Tag::factory()->create([
        'type' => Tag::TYPE_NORMAL,
        'code' => 'OTHER',
        'name' => 'GREYWHITE',
        'item_type' => 0,
    ]);

    expect(fn () => Tag::findOrCreateWarnaTag('GREYWHITE'))
        ->toThrow(InvalidArgumentException::class);

    expect($existing->fresh())->not->toBeNull()
        ->and(Tag::query()->whereRaw('UPPER(TRIM(name)) = ?', ['GREYWHITE'])->count())->toBe(1);
});

it('recovers from a tags.name unique violation when creating a warna tag', function () {
    $existing = Tag::factory()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => 'GREYWHITE',
        'name' => 'GREYWHITE',
        'item_type' => 0,
    ]);

    enforceProductionTagsNameUnique();

    try {
        $method = new ReflectionMethod(Tag::class, 'createUniqueByName');
        $method->setAccessible(true);
        $resolved = $method->invoke(null, [
            'type' => Tag::TYPE_WARNA,
            'code' => 'GREYWHITE',
            'name' => 'GREYWHITE',
            'item_type' => 0,
        ], Tag::TYPE_WARNA, 'Warna');

        expect($resolved->id)->toBe($existing->id);
    } finally {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
});
