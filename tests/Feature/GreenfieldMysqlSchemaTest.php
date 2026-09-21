<?php

use App\Support\GreenfieldMysqlSchema;
use Illuminate\Support\Facades\Schema;

it('does not treat sqlite dev schema as greenfield mysql', function () {
    expect(Schema::getConnection()->getDriverName())->toBe('sqlite')
        ->and(GreenfieldMysqlSchema::usesBigintLegacyPrimaryKeys())->toBeFalse();
});

it('documents production bootstrap skips greenfield mysql in migration source', function () {
    $source = file_get_contents(base_path('database/migrations/2026_08_13_100000_production_database_bootstrap.php'));

    expect($source)->toContain('GreenfieldMysqlSchema::usesBigintLegacyPrimaryKeys()');
});

it('documents reapply production defaults skips greenfield mysql in migration source', function () {
    $source = file_get_contents(base_path('database/migrations/2026_08_21_100000_reapply_production_defaults_and_int_keys.php'));

    expect($source)->toContain('GreenfieldMysqlSchema::usesBigintLegacyPrimaryKeys()');
});
