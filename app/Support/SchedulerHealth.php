<?php

namespace App\Support;

use App\Models\ScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class SchedulerHealth
{
  /**
   * @return array{
   *     healthy: bool,
   *     stale: bool,
   *     latest_active_run_at: ?Carbon,
   *     process_queue_last_run_at: ?Carbon,
   *     shopee_ads_last_run_at: ?Carbon,
   *     active_task_count: int,
   *     message: string,
   * }
   */
    public static function snapshot(?ScheduledTask $shopeeAdsTask = null): array
    {
        $activeTasks = ScheduledTask::query()->where('active', true)->get(['command', 'last_run_at']);
        $latestRun = $activeTasks->max('last_run_at');
        $latestActiveRunAt = $latestRun ? Carbon::parse($latestRun) : null;

        $processQueueLastRunAt = $activeTasks
            ->firstWhere('command', 'app:process-queue')
            ?->last_run_at;

        $shopeeAdsLastRunAt = $shopeeAdsTask?->last_run_at
            ?? $activeTasks->firstWhere('command', 'shopee-ads:process')?->last_run_at;

        $stale = $latestActiveRunAt === null
            || $latestActiveRunAt->lt(now()->subMinutes(5));

        $healthy = ! $stale;

        $message = match (true) {
            $activeTasks->isEmpty() => 'Tidak ada cron task aktif di Cron Manager.',
            $latestActiveRunAt === null => 'Laravel scheduler belum pernah jalan. Pastikan OS cron memanggil php artisan schedule:run tiap menit.',
            $stale => 'Laravel scheduler terakhir jalan '.$latestActiveRunAt->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB — lebih dari 5 menit lalu.',
            default => 'Laravel scheduler aktif (last run '.$latestActiveRunAt->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB).',
        };

        return [
            'healthy' => $healthy,
            'stale' => $stale,
            'latest_active_run_at' => $latestActiveRunAt,
            'process_queue_last_run_at' => $processQueueLastRunAt,
            'shopee_ads_last_run_at' => $shopeeAdsLastRunAt,
            'active_task_count' => $activeTasks->count(),
            'message' => $message,
        ];
    }

    public static function clearScheduleCache(): void
    {
        try {
            Artisan::call('schedule:clear-cache');
        } catch (\Throwable) {
            // schedule:clear-cache is optional; ignore if unavailable.
        }
    }
}
