<?php

use App\Models\DataRetentionRun;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataRetentionService;
use App\Services\PermissionGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

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

it('maps calendar years to L10 upper-bound partition names', function () {
    expect($this->retention->partitionNameForCalendarYear(2014))->toBe('p2015')
        ->and($this->retention->partitionNameForCalendarYear(2020))->toBe('p2021')
        ->and($this->retention->calendarYearUsesPartitionDrop(2014))->toBeTrue()
        ->and($this->retention->calendarYearUsesPartitionDrop(2013))->toBeFalse();
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

it('allows retrying cleanup when a prior run was marked cleaned but rows remain', function () {
    $this->travelTo('2026-08-28');

    $transaction = Transaction::factory()->create(['date' => '2020-01-10', 'description' => 'still here']);
    DB::table('transaction_details')->insert([
        'id' => 9003,
        'transaction_id' => $transaction->id,
        'item_id' => Item::factory()->create()->id,
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

    DataRetentionRun::query()->where('year', 2020)->update([
        'status' => DataRetentionRun::STATUS_CLEANED,
        'cleanup_finished_at' => now(),
    ]);

    $this->retention->cleanupLiveYear(2020);

    expect(DB::table('transactions')->whereBetween('date', ['2020-01-01', '2020-12-31'])->count())->toBe(0)
        ->and(DataRetentionRun::query()->where('year', 2020)->value('status'))->toBe(DataRetentionRun::STATUS_CLEANED);
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

    $this->actingAs($user)
        ->get(route('data-retention.item-purge.index'))
        ->assertSuccessful()
        ->assertSee('Selective Item Purge');
});

it('renders cleanup preview with partition metadata without number_format errors', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    $this->actingAs($user)
        ->from(route('data-retention.index'))
        ->post(route('data-retention.preview-cleanup'), ['year' => 2014])
        ->assertRedirect(route('data-retention.index'));

    $this->actingAs($user)
        ->get(route('data-retention.index'))
        ->assertSuccessful()
        ->assertSee('Live cleanup preview')
        ->assertSee('p2015')
        ->assertSee('Partition drop:');
});

it('allows archive-view users to browse archive but not data retention', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');

    User::factory()->create(['id' => 1]);
    $user = User::factory()->create();
    expect($user->id)->not->toBe(User::SUPERADMIN_ID);
    $user->givePermissionTo('archive-view');

    $this->actingAs($user)
        ->get(route('archive.index'))
        ->assertSuccessful()
        ->assertSee('Archive');

    $this->actingAs($user)
        ->get(route('data-retention.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('data-retention.item-purge.index'))
        ->assertForbidden();
});

it('denies data retention even when legacy manage permission was granted', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');
    Permission::firstOrCreate(['name' => 'data-retention-manage', 'guard_name' => 'web']);

    User::factory()->create(['id' => 1]);
    $user = User::factory()->create();
    expect($user->id)->not->toBe(User::SUPERADMIN_ID);
    $user->givePermissionTo(['archive-view', 'data-retention-manage']);

    $this->actingAs($user)
        ->get(route('archive.index'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('data-retention.index'))
        ->assertForbidden();
});

it('purges soft-deleted orphan items without checking deleted_at', function () {
    $this->travelTo('2026-08-28');

    $item = Item::factory()->create(['created_at' => '2014-03-01']);
    $item->delete();

    $result = $this->retention->purgeOrphanItemsFromLive(false);

    expect($result['items'])->toBe(1)
        ->and(DB::table('items')->where('id', $item->id)->exists())->toBeFalse();
});

it('purges orphan item groups after items are removed', function () {
    $this->travelTo('2026-08-28');

    $groupId = DB::table('item_group')->insertGetId([
        'name' => 'Old Group',
        'master' => 'OLD',
        'variant' => 'RED',
    ]);

    $item = Item::factory()->create([
        'created_at' => '2014-01-01',
        'group_id' => $groupId,
    ]);

    $this->retention->purgeOrphanItemsFromLive(false);

    expect(DB::table('item_group')->where('id', $groupId)->exists())->toBeFalse();
});

it('purges orphan customers and related stat rows', function () {
    $this->travelTo('2026-08-28');

    $customer = \App\Models\Addrbook::factory()->customer()->create(['created_at' => '2014-06-01']);
    DB::table('customerstat')->insert([
        'customer_id' => $customer->id,
        'balance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $purged = $this->retention->purgeOrphanAddrbooksFromLive(\App\Models\Addrbook::TYPE_CUSTOMER, false);

    expect($purged)->toBe(1)
        ->and(DB::table('customers')->where('id', $customer->id)->exists())->toBeFalse()
        ->and(DB::table('customerstat')->where('customer_id', $customer->id)->exists())->toBeFalse();
});

it('purges orphan items with warehouse stock on selective purge', function () {
    $this->travelTo('2026-08-28');

    $item = Item::factory()->create(['created_at' => '2014-04-01']);
    DB::table('warehouse_item')->insert([
        'item_id' => $item->id,
        'warehouse_id' => \App\Models\Addrbook::factory()->warehouse()->create()->id,
        'warehouse_type' => '2',
        'quantity' => 12,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $this->retention->purgeSelectableOrphanItemsFromLive(
        maxId: $item->id,
    );

    expect($result['items'])->toBe(1)
        ->and(DB::table('items')->where('id', $item->id)->exists())->toBeFalse()
        ->and(DB::table('warehouse_item')->where('item_id', $item->id)->exists())->toBeFalse();
});

it('purges orphan items with migration-touched created_at when bulk timestamp is configured', function () {
    $this->travelTo('2026-08-28');

    config(['data_retention.legacy_bulk_touch_timestamps' => ['2026-08-24 02:58:40']]);

    $item = Item::factory()->create(['created_at' => '2026-08-24 02:58:40']);

    $result = $this->retention->purgeOrphanItemsFromLive(
        dryRun: false,
        cutoffYear: 2022,
    );

    expect($result['items'])->toBe(1)
        ->and(DB::table('items')->where('id', $item->id)->exists())->toBeFalse();
});

it('does not purge recent orphan items that were not bulk-touched', function () {
    $this->travelTo('2026-08-28');

    config(['data_retention.legacy_bulk_touch_timestamps' => ['2026-08-24 02:58:40']]);

    $item = Item::factory()->create(['created_at' => '2026-08-27 12:00:00']);

    $result = $this->retention->purgeOrphanItemsFromLive(
        dryRun: false,
        cutoffYear: 2022,
    );

    expect($result['items'])->toBe(0)
        ->and(DB::table('items')->where('id', $item->id)->exists())->toBeTrue();
});

it('lists orphan items with id lte max id on selective preview including soft-deleted', function () {
    $this->travelTo('2026-08-28');

    $orphan = Item::factory()->create(['created_at' => '2026-08-27 12:00:00']);
    $softDeleted = Item::factory()->create(['created_at' => '2026-08-27 12:00:00']);
    $softDeleted->delete();
    $withTx = Item::factory()->create(['created_at' => '2026-08-24 02:58:40']);
    $transaction = Transaction::factory()->create(['date' => '2020-01-10']);
    DB::table('transaction_details')->insert([
        'id' => 9101,
        'transaction_id' => $transaction->id,
        'item_id' => $withTx->id,
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
    $aboveMax = Item::factory()->create(['created_at' => '2014-01-01']);

    $maxId = max($orphan->id, $softDeleted->id, $withTx->id);
    expect($aboveMax->id)->toBeGreaterThan($maxId);

    $preview = $this->retention->previewSelectableItemPurge(
        maxId: $maxId,
        perPage: 100,
    );

    expect($preview->total())->toBe(2)
        ->and(collect($preview->items())->pluck('id')->all())->toEqualCanonicalizing([$orphan->id, $softDeleted->id]);
});

it('paginates selective item purge preview at 100 rows sorted by id asc', function () {
    $this->travelTo('2026-08-28');

    for ($i = 0; $i < 105; $i++) {
        Item::factory()->create(['created_at' => '2014-01-01']);
    }

    $maxId = (int) DB::table('items')->max('id');

    $pageOne = $this->retention->previewSelectableItemPurge(
        maxId: $maxId,
        perPage: 100,
    );

    expect($pageOne->total())->toBe(105)
        ->and($pageOne->perPage())->toBe(100)
        ->and($pageOne->count())->toBe(100)
        ->and($pageOne->first()['id'])->toBeLessThan($pageOne->last()['id']);

    request()->merge(['page' => 2]);

    $pageTwo = $this->retention->previewSelectableItemPurge(
        maxId: $maxId,
        perPage: 100,
    );

    expect($pageTwo->currentPage())->toBe(2)
        ->and($pageTwo->count())->toBe(5);
});

it('excludes kept item ids from selective orphan purge', function () {
    $this->travelTo('2026-08-28');

    $keep = Item::factory()->create(['created_at' => '2014-01-01']);
    $purge = Item::factory()->create(['created_at' => '2014-02-01']);
    $maxId = max($keep->id, $purge->id);

    $result = $this->retention->purgeSelectableOrphanItemsByIds(
        maxId: $maxId,
        itemType: null,
        itemIds: [$purge->id],
    );

    expect($result['items'])->toBe(1)
        ->and(DB::table('items')->where('id', $keep->id)->exists())->toBeTrue()
        ->and(DB::table('items')->where('id', $purge->id)->exists())->toBeFalse();
});

it('renders selective item purge with keep checkboxes and pagination', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    $this->travelTo('2026-08-28');
    $orphan = Item::factory()->create(['created_at' => '2026-08-24 02:58:40']);

    $this->actingAs($user)
        ->get(route('data-retention.item-purge.index', ['max_id' => $orphan->id]))
        ->assertSuccessful()
        ->assertSee('Selective Item Purge')
        ->assertSee('Max item id')
        ->assertSee('Keep')
        ->assertSee('#'.$orphan->id)
        ->assertSee('Purge 1 on this page')
        ->assertSee('this page only');
});

it('purges only unchecked items on the submitted page', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    $this->travelTo('2026-08-28');

    $keep = Item::factory()->create(['created_at' => '2026-08-24 02:58:40']);
    $purge = Item::factory()->create(['created_at' => '2026-08-24 02:58:40']);
    $maxId = max($keep->id, $purge->id);

    $this->actingAs($user)
        ->post(route('data-retention.item-purge.purge'), [
            'max_id' => $maxId,
            'page' => 1,
            'page_item_ids' => [$keep->id, $purge->id],
            'keep_ids' => [$keep->id],
            'confirm' => 'PURGE-SELECTED-ITEMS',
        ])
        ->assertRedirect(route('data-retention.item-purge.index', ['max_id' => $maxId]));

    expect(DB::table('items')->where('id', $keep->id)->exists())->toBeTrue()
        ->and(DB::table('items')->where('id', $purge->id)->exists())->toBeFalse();
});

it('does not purge items on other pages when submitting one page', function () {
    app(PermissionGenerator::class)->generateForModule('DataRetentionRun');
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);

    $this->travelTo('2026-08-28');

    $pageOneItems = collect();
    for ($i = 0; $i < 100; $i++) {
        $pageOneItems->push(Item::factory()->create(['created_at' => '2014-01-01']));
    }

    $pageTwoItem = Item::factory()->create(['created_at' => '2014-01-01']);
    $maxId = (int) DB::table('items')->max('id');

    $pageOneIds = $pageOneItems->pluck('id')->all();

    $this->actingAs($user)
        ->post(route('data-retention.item-purge.purge'), [
            'max_id' => $maxId,
            'page' => 1,
            'page_item_ids' => $pageOneIds,
            'confirm' => 'PURGE-SELECTED-ITEMS',
        ])
        ->assertRedirect(route('data-retention.item-purge.index', ['max_id' => $maxId]));

    expect(DB::table('items')->where('id', $pageTwoItem->id)->exists())->toBeTrue()
        ->and(DB::table('items')->whereIn('id', $pageOneIds)->count())->toBe(0);
});
