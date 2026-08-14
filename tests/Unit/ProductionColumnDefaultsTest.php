<?php

use App\Models\Addrbook;
use App\Support\ProductionColumnDefaults;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

function mockMysqlSchemaForCustomerColumns(array $columns): void
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    Schema::partialMock()
        ->shouldReceive('getConnection')
        ->andReturn($connection);
    Schema::partialMock()
        ->shouldReceive('hasColumn')
        ->andReturnUsing(fn (string $table, string $column) => $table === 'customers' && in_array($column, $columns, true));
}

it('fills null legacy customer columns when applying production defaults on mysql', function () {
    mockMysqlSchemaForCustomerColumns(['email', 'phone', 'description']);

    $addrbook = new Addrbook([
        'email' => null,
        'phone' => null,
        'description' => null,
    ]);

    ProductionColumnDefaults::apply($addrbook);

    expect($addrbook->email)->toBe('')
        ->and($addrbook->phone)->toBe('')
        ->and($addrbook->description)->toBe('');
});

it('fills null customer email on update via model events on mysql', function () {
    mockMysqlSchemaForCustomerColumns(['email']);

    $addrbook = Addrbook::withoutEvents(fn () => Addrbook::create([
        'name' => 'Warehouse',
        'type' => Addrbook::TYPE_WAREHOUSE,
        'email' => 'old@example.com',
    ]));

    $addrbook->email = null;
    $addrbook->save();

    expect($addrbook->fresh()->email)->toBe('');
});
