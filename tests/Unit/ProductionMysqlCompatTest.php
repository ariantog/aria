<?php

use App\Support\ProductionMysqlCompat;

uses(Tests\TestCase::class);

it('builds zero-date where clauses for every legacy pattern', function () {
    $clauses = ProductionMysqlCompat::zeroDateWhereClauses('birthdate');

    expect($clauses)->toContain("`birthdate` = '0000-00-00'");
    expect($clauses)->toContain("CAST(`birthdate` AS CHAR) LIKE '0000-00-00%'");
    expect($clauses)->toContain("(`birthdate` IS NOT NULL AND `birthdate` < '1000-01-01')");
});

it('uses null for nullable zero-date columns', function () {
    expect(ProductionMysqlCompat::zeroDateReplacement('date', 'YES'))->toBe('NULL');
    expect(ProductionMysqlCompat::zeroDateReplacement('datetime', 'YES'))->toBe('NULL');
});

it('uses sentinel values for not-null zero-date columns', function () {
    expect(ProductionMysqlCompat::zeroDateReplacement('date', 'NO'))->toBe("'1970-01-01'");
    expect(ProductionMysqlCompat::zeroDateReplacement('datetime', 'NO'))->toBe("'1970-01-01 00:00:00'");
    expect(ProductionMysqlCompat::zeroDateReplacement('timestamp', 'NO'))->toBe("'1970-01-01 00:00:00'");
    expect(ProductionMysqlCompat::zeroDateReplacement('year', 'NO'))->toBe("'1970-01-01'");
});

it('no-ops zero-date normalization on sqlite', function () {
    if (ProductionMysqlCompat::isMysql()) {
        expect(true)->toBeTrue();

        return;
    }

    ProductionMysqlCompat::normalizeZeroDatesForDatabase();
    ProductionMysqlCompat::normalizeZeroDatesOnTable('customers');
    ProductionMysqlCompat::alterTable('customers', fn () => expect(true)->toBeTrue());
});
