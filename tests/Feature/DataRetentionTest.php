<?php

use App\Models\DataRetentionRun;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataRetentionService;
use App\Services\PermissionGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'data_retention.retention_years' => 5,
        'database.connections.archive' => [
            'driver' => 'sqlite',
            'database' => database_path('testing_archive.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    if (file_exists(database_path('testing_archive.sqlite'))) {
        unlink(database_path('testing_archive.sqlite'));
    }

    touch(database_path('testing_archive.sqlite'));

    seedMinimalSchemaForRetention();
    $this->retention = app(DataRetentionService::class);
});

afterEach(function () {
    if (file_exists(database_path('testing_archive.sqlite'))) {
        unlink(database_path('testing_archive.sqlite'));
    }
});

function seedMinimalSchemaForRetention(): void
{
    $tables = ['transactions', 'transaction_details', 'customers', 'items', 'item_group', 'warehouse_item'];

    foreach ($tables as $table) {
        if (Schema::connection('archive')->hasTable($table)) {
            continue;
        }

        if (! Schema::hasTable($table)) {
            continue;
        }

        $ddl = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

        if ($ddl?->sql) {
            DB::connection('archive')->statement($ddl->sql);
        }
    }

    if (DB::connection('archive')->getDriverName() === 'sqlite') {
        DB::connection('archive')->statement('PRAGMA foreign_keys = OFF');
    }
}

it('computes eligible archive years from retention window', function () {
    $this->travelTo('2026-08-28');

    expect($this->retention->liveRetentionStartYear())->toBe(2022)
        ->and($this->retention->yearsEligibleForArchive())->toBe([]);
});

it('copies a calendar year to the archive database', function () {
    $this->travelTo('2026-08-28');

    $customer = \App\Models\Addrbook::factory()->create(['created_at' => '2020-03-01']);
    $item = Item::factory()->create(['created_at' => '2020-05-01']);
    $transaction = Transaction::factory()->create([
        'date' => '2020-06-15',
        'sender_id' => $customer->id,
        'receiver_id' => $customer->id,
        'description' => 'Old sale',
    ]);

    DB::table('transaction_details')->insert([
        'id' => 9001,
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 1000,
        'discount' => 0,
        'total' => 1000,
        'date' => '2020-06-15',
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $customer->id,
        'receiver_id' => $customer->id,
        'transaction_disc' => 0,
    ]);

    $result = $this->retention->archiveYear(2020);

    expect($result['transactions'])->toBe(1)
        ->and($result['details'])->toBe(1)
        ->and(DB::connection('archive')->table('transactions')->count())->toBe(1)
        ->and(DB::connection('archive')->table('transaction_details')->count())->toBe(1)
        ->and(DB::connection('archive')->table('items')->where('id', $item->id)->exists())->toBeTrue();

    $run = DataRetentionRun::query()->where('year', 2020)->first();
    expect($run->status)->toBe(DataRetentionRun::STATUS_ARCHIVED);
});

it('removes an archived year from live using row delete fallback on sqlite', function () {
    $this->travelTo('2026-08-28');

    $item = Item::factory()->create();
    $transaction = Transaction::factory()->create(['date' => '2020-01-10', 'description' => 'purge me']);
    DB::table('transaction_details')->insert([
        'id' => 9002,
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 100,
        'discount' => 0,
        'total' => 100,
        'date' => '2020-01-10',
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => 1,
        'receiver_id' => 1,
        'transaction_disc' => 0,
    ]);

    $this->retention->archiveYear(2020);
    $result = $this->retention->cleanupLiveYear(2020);

    expect($result['transactions'])->toBe(1)
        ->and(DB::table('transactions')->whereBetween('date', ['2020-01-01', '2020-12-31'])->count())->toBe(0)
        ->and(DB::table('transaction_details')->whereBetween('date', ['2020-01-01', '2020-12-31'])->count())->toBe(0);

    $run = DataRetentionRun::query()->where('year', 2020)->first();
    expect($run->status)->toBe(DataRetentionRun::STATUS_CLEANED);
});

it('renders archive pages for superadmin', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    $this->actingAs($user)
        ->get(route('archive.index'))
        ->assertSuccessful()
        ->assertSee('Archive');

    $this->actingAs($user)
        ->get(route('data-retention.index'))
        ->assertSuccessful()
        ->assertSee('Data Retention');
});
