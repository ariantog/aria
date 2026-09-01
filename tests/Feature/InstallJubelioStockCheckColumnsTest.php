<?php

use App\Models\JubelioStockCheck;
use App\Models\JubelioStockDiscrepancy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function recreateLegacyJubelioStockCheckTables(): void
{
    Schema::dropIfExists('jubelio_stock_discrepancies');
    Schema::dropIfExists('jubelio_stock_checks');

    Schema::create('jubelio_stock_checks', function (Blueprint $table) {
        $table->id();
        $table->integer('page_tracking')->default(1);
        $table->string('status')->default('created');
        $table->timestamps();
    });

    Schema::create('jubelio_stock_discrepancies', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('jubelio_stock_check_id');
        $table->unsignedBigInteger('jubelio_item_id');
        $table->integer('jubelio_location_id');
        $table->unsignedBigInteger('item_id')->nullable();
        $table->string('jubelio_location_name')->nullable();
        $table->integer('warehouse_id');
        $table->decimal('aria_qty', 15, 2);
        $table->decimal('jubelio_qty', 15, 2);
        $table->timestamps();
    });
}

function migrateJubelioStockCheckColumns(): void
{
    DB::table('migrations')
        ->where('migration', '2026_09_01_210000_install_jubelio_stock_check_columns')
        ->delete();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_09_01_210000_install_jubelio_stock_check_columns.php',
        '--force' => true,
    ]);
}

it('adds stock-check columns onto the legacy L10 table shape', function () {
    recreateLegacyJubelioStockCheckTables();

    expect(Schema::hasColumn('jubelio_stock_checks', 'sync_cursor'))->toBeFalse()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'per_type_limit'))->toBeFalse()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'demand_days'))->toBeFalse()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'target_discrepancies'))->toBeFalse()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'scan_round'))->toBeFalse()
        ->and(Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_on_hand'))->toBeFalse()
        ->and(Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_available'))->toBeFalse();

    migrateJubelioStockCheckColumns();

    expect(Schema::hasColumn('jubelio_stock_checks', 'sync_cursor'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'per_type_limit'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'demand_days'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'target_discrepancies'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_checks', 'scan_round'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_on_hand'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_on_order'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_available'))->toBeTrue()
        ->and(Schema::hasColumn('jubelio_stock_discrepancies', 'jubelio_reserved'))->toBeTrue();

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
        'status' => 'created',
    ]);

    expect($job->exists)->toBeTrue()
        ->and($job->sync_cursor)->toBe(0)
        ->and($job->per_type_limit)->toBe(50);

    $discrepancy = JubelioStockDiscrepancy::create([
        'jubelio_stock_check_id' => $job->id,
        'jubelio_item_id' => 1,
        'jubelio_location_id' => 10,
        'item_id' => null,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => 1,
        'aria_qty' => 10,
        'jubelio_qty' => 9,
        'jubelio_on_hand' => 10,
        'jubelio_on_order' => 0,
        'jubelio_available' => 9,
        'jubelio_reserved' => 1,
    ]);

    expect($discrepancy->exists)->toBeTrue()
        ->and((float) $discrepancy->jubelio_available)->toBe(9.0);
});

it('is safe to re-run when stock-check columns already exist', function () {
    migrateJubelioStockCheckColumns();

    expect(Schema::hasColumn('jubelio_stock_checks', 'sync_cursor'))->toBeTrue();

    $job = JubelioStockCheck::create([
        'sync_cursor' => 0,
        'per_type_limit' => 50,
        'demand_days' => 90,
        'target_discrepancies' => 50,
        'scan_round' => 0,
        'status' => 'created',
    ]);

    expect($job->exists)->toBeTrue();
});
