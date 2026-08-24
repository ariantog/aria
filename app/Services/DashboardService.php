<?php

namespace App\Services;

use App\Models\Crongetorder;
use App\Models\ItemStockNotification;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\ScheduledTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    public const QUEUE_WARN_THRESHOLD = 1;

    public const QUEUE_CRITICAL_THRESHOLD = 50;

    public function __construct(
        protected JubelioService $jubelioService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $canJubelio = Gate::forUser($user)->allows(Jubelio::getPermissions()['view']);
        $canStockAlerts = Gate::forUser($user)->allows(ItemStockNotification::getPermissions()['view']);
        $canCron = Gate::forUser($user)->allows(ScheduledTask::getPermissions()['view']);

        $data = [
            'can' => [
                'jubelio' => $canJubelio,
                'stock_alerts' => $canStockAlerts,
                'queue' => $canCron,
                'cron_manager' => $canCron,
            ],
            'has_ops_panel' => $canJubelio || $canStockAlerts || $canCron,
        ];

        if ($canJubelio) {
            $data['jubelio'] = $this->jubelioPanel();
        }

        if ($canStockAlerts) {
            $data['stock_alerts'] = $this->stockAlertsPanel();
        }

        if ($canCron || $canJubelio) {
            $data['queue'] = $this->queuePanel();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function jubelioPanel(): array
    {
        $connection = $this->jubelioService->getConnectionStatus();

        $stats = Jubelioorder::query()
            ->selectRaw('COUNT(CASE WHEN status = 0 THEN 1 END) as pending, COUNT(CASE WHEN status = 1 AND error_type = 1 THEN 1 END) as error')
            ->first();

        $runningImport = Crongetorder::query()
            ->where('status', 0)
            ->latest('id')
            ->first();

        return [
            'connection' => $connection,
            'connection_state' => $this->resolveJubelioConnectionState($connection),
            'order_stats' => [
                'pending' => (int) ($stats->pending ?? 0),
                'error' => (int) ($stats->error ?? 0),
            ],
            'running_import' => $runningImport,
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function resolveJubelioConnectionState(array $connection): string
    {
        if (! ($connection['jubelio_active'] ?? false)) {
            return 'inactive';
        }

        if (! ($connection['configured'] ?? false)) {
            return 'unconfigured';
        }

        if (! ($connection['has_token'] ?? false)) {
            return 'no_token';
        }

        if ($connection['is_expired'] ?? true) {
            return 'expired';
        }

        if (($connection['last_api_check_ok'] ?? null) === false) {
            return 'api_failed';
        }

        return 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    protected function stockAlertsPanel(): array
    {
        return [
            'unread_count' => ItemStockNotification::query()->unread()->count(),
            'recent' => ItemStockNotification::query()
                ->unread()
                ->with([
                    'item:id,code,name',
                    'soldOutWarehouse:id,name',
                    'sourceWarehouse:id,name',
                ])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function queuePanel(): array
    {
        $pendingJobs = (int) DB::table('jobs')->count();
        $processQueueTask = ScheduledTask::query()
            ->where('command', 'app:process-queue')
            ->first();

        $level = match (true) {
            $pendingJobs >= self::QUEUE_CRITICAL_THRESHOLD => 'critical',
            $pendingJobs >= self::QUEUE_WARN_THRESHOLD => 'warning',
            default => 'ok',
        };

        if ($processQueueTask !== null && ! $processQueueTask->active) {
            $level = 'critical';
        }

        return [
            'pending_jobs' => $pendingJobs,
            'level' => $level,
            'process_queue_active' => $processQueueTask?->active,
        ];
    }
}
