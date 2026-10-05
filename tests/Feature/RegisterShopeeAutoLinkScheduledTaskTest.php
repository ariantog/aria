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
        ->and($row->frequency)->toBe('everyFiveMinutes')
        ->and($row->active)->toBeTrue();
});
