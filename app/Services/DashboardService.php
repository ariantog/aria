<?php

namespace App\Services;

use App\Models\Crongetorder;
use App\Models\ItemStockNotification;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\JubelioStockCheck;
use App\Models\Jubelioreturn;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Models\WarehouseArrangementRefreshJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    public const QUEUE_WARN_THRESHOLD = 1;

    public const QUEUE_CRITICAL_THRESHOLD = 50;

    public function __construct(
        protected JubelioService $jubelioService,
        protected BookClosingService $bookClosingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $canJubelio = Gate::forUser($user)->allows(Jubelio::getPermissions()['view']);
        $canStockCheck = Gate::forUser($user)->allows(Jubelio::getPermissions()['stock-check']);
        $canStockAlerts = Gate::forUser($user)->allows(ItemStockNotification::getPermissions()['view']);
        $canCron = Gate::forUser($user)->allows(ScheduledTask::getPermissions()['view']);
        $canBookClosing = Gate::forUser($user)->allows('transactions-list');
        $canWarehouseArrangement = Gate::forUser($user)->allows('report-warehouse-arrangement');

        $data = [
            'can' => [
                'jubelio' => $canJubelio,
                'jubelio_stock_check' => $canStockCheck,
                'stock_alerts' => $canStockAlerts,
                'queue' => $canCron || $canJubelio,
                'cron_manager' => $canCron,
                'book_closing' => $canBookClosing,
                'warehouse_arrangement' => $canWarehouseArrangement,
            ],
            'has_ops_panel' => $canJubelio
                || $canStockCheck
                || $canStockAlerts
                || $canCron
                || $canBookClosing
                || $canWarehouseArrangement,
        ];

        if ($canJubelio) {
            $data['jubelio'] = $this->jubelioPanel();
        }

        if ($canStockCheck) {
            $data['jubelio_stock_check'] = $this->jubelioStockCheckPanel();
        }

        if ($canStockAlerts) {
            $data['stock_alerts'] = $this->stockAlertsPanel();
        }

        if ($canCron || $canJubelio) {
            $data['queue'] = $this->queuePanel();
        }

        if ($canCron) {
            $data['cron'] = $this->cronPanel();
        }

        if ($canBookClosing) {
            $data['book_closing'] = $this->bookClosingPanel();
        }

        if ($canWarehouseArrangement) {
            $data['warehouse_arrangement'] = $this->warehouseArrangementPanel();
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
            'pending_cancellations' => (int) Jubelioreturn::query()->where('status', 0)->count(),
            'running_import' => $runningImport,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function jubelioStockCheckPanel(): array
    {
        $activeJob = JubelioStockCheck::query()
            ->whereIn('status', ['created', 'processing'])
            ->latest('id')
            ->first();

        $latestCompleted = JubelioStockCheck::query()
            ->where('status', 'completed')
            ->withCount('discrepancies')
            ->latest('id')
            ->first();

        return [
            'active_job' => $activeJob,
            'latest_completed' => $latestCompleted,
            'latest_discrepancies' => (int) ($latestCompleted?->discrepancies_count ?? 0),
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

    /**
     * @return array<string, mixed>
     */
    protected function cronPanel(): array
    {
        $disabledTasks = ScheduledTask::query()
            ->where('active', false)
            ->orderBy('name')
            ->get(['id', 'name', 'command']);

        return [
            'disabled_count' => $disabledTasks->count(),
            'disabled_tasks' => $disabledTasks->take(5)->values(),
            'total_tasks' => (int) ScheduledTask::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookClosingPanel(): array
    {
        return $this->bookClosingService->getClosingReminder();
    }

    /**
     * @return array<string, mixed>
     */
    protected function warehouseArrangementPanel(): array
    {
        $activeJobs = WarehouseArrangementRefreshJob::query()
            ->whereIn('status', [
                WarehouseArrangementRefreshJob::STATUS_CREATED,
                WarehouseArrangementRefreshJob::STATUS_PROCESSING,
            ])
            ->with('destinationWarehouse:id,name')
            ->latest('id')
            ->limit(3)
            ->get();

        return [
            'active_jobs' => $activeJobs,
            'active_count' => $activeJobs->count(),
        ];
    }
}
