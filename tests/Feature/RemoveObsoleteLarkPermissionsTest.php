<?php

use App\Support\ObsoleteLarkPermissions;
use Spatie\Permission\Models\Permission;

test('obsolete lark permissions can be removed', function () {
    foreach (ObsoleteLarkPermissions::NAMES as $name) {
        Permission::findOrCreate($name, 'web');
    }

    expect(ObsoleteLarkPermissions::existing()->count())->toBe(count(ObsoleteLarkPermissions::NAMES));

    $deleted = ObsoleteLarkPermissions::remove();

    expect($deleted)->toBe(count(ObsoleteLarkPermissions::NAMES));
    expect(ObsoleteLarkPermissions::existing()->count())->toBe(0);
});