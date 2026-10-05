<?php

use App\Models\ScheduledTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('registers shopee auto link cron via migration on existing scheduled_tasks table', function () {
    expect(Schema::hasTable('scheduled_tasks'))->toBeTrue();

    ScheduledTask::query()->where('command', 'app:shopee-auto-link-items')->delete();

    DB::table('migrations')->where('migration', '2026_10_05_110000_register_shopee_auto_link_scheduled_task')->delete();

    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_10_05_110000_register_shopee_auto_link_scheduled_task.php',
    ])->assertSuccessful();

    $row = ScheduledTask::query()->where('command', 'app:shopee-auto-link-items')->first();

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Shopee Auto Link Items')
        ->and($row->frequency)->toBe('hourly')
        ->and($row->active)->toBeTrue();
});

it('migrates existing shopee auto link cron to hourly frequency', function () {
    ScheduledTask::query()->updateOrCreate(
        ['command' => 'app:shopee-auto-link-items'],
        [
            'name' => 'Shopee Auto Link Items',
            'frequency' => 'everyFiveMinutes',
            'active' => true,
            'description' => 'old',
        ]
    );

    DB::table('migrations')->where('migration', '2026_10_05_120000_shopee_auto_link_hourly_batch')->delete();

    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_10_05_120000_shopee_auto_link_hourly_batch.php',
    ])->assertSuccessful();

    expect(ScheduledTask::query()->where('command', 'app:shopee-auto-link-items')->value('frequency'))
        ->toBe('hourly');
});
