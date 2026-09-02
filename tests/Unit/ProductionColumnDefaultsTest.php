<?php

use App\Models\Addrbook;
use App\Models\Produksi;
use App\Support\ProductionColumnDefaults;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

function mockMysqlSchemaForTableColumns(string $table, array $columns): void
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    Schema::partialMock()
        ->shouldReceive('getConnection')
        ->andReturn($connection);
    Schema::partialMock()
        ->shouldReceive('hasColumn')
        ->andReturnUsing(fn (string $actualTable, string $column) => $actualTable === $table && in_array($column, $columns, true));
}

function mockMysqlSchemaForCustomerColumns(array $columns): void
{
    mockMysqlSchemaForTableColumns('customers', $columns);
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

it('leaves null produksi worker ids unset when applying production defaults on mysql', function () {
    mockMysqlSchemaForTableColumns('prod_produksi', ['qc_id', 'jahit_id', 'pritil_id', 'item_id']);

    $produksi = new Produksi([
        'qc_id' => null,
        'jahit_id' => null,
        'pritil_id' => null,
        'item_id' => null,
    ]);

    ProductionColumnDefaults::apply($produksi);

    expect($produksi->qc_id)->toBeNull()
        ->and($produksi->jahit_id)->toBeNull()
        ->and($produksi->pritil_id)->toBeNull()
        ->and($produksi->item_id)->toBe(0);
});
