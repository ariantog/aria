<?php

use App\Services\LegacySqlParser;

it('parses acl rows from sql dump', function () {
    $sql = <<<'SQL'
INSERT INTO `acl` (`role_id`, `action`, `app_id`, `created_at`, `updated_at`) VALUES
(2, 'index', 11, '2024-01-01 00:00:00', '0000-00-00 00:00:00'),
(2, 'sell', 11, '2024-01-01 00:00:00', '0000-00-00 00:00:00');
SQL;

    $rows = (new LegacySqlParser)->parseAcl($sql);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['role_id' => 2, 'action' => 'index', 'app_id' => 11]);
});

it('parses location_customer pivot rows', function () {
    $sql = <<<'SQL'
INSERT INTO `location_customer` (`location_id`, `customer_id`) VALUES
(1, 100),
(3, 200);
SQL;

    $rows = (new LegacySqlParser)->parseLocationCustomer($sql);

    expect($rows)->toBe([
        ['location_id' => 1, 'customer_id' => 100],
        ['location_id' => 3, 'customer_id' => 200],
    ]);
});

it('parses the bundled legacy acl dump', function () {
    $path = dirname(__DIR__, 2).'/database/acl/old_acl.sql';
    $data = (new LegacySqlParser)->parseFile($path);

    expect($data['acl'])->not->toBeEmpty()
        ->and($data['roles'])->not->toBeEmpty()
        ->and($data['users'])->not->toBeEmpty()
        ->and($data['locations'])->not->toBeEmpty()
        ->and($data['location_customer'])->not->toBeEmpty();
});
