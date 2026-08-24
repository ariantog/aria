<?php

namespace App\Services;

use App\Models\Crongetorder;
use App\Models\ItemStockNotification;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\JubelioStockCheck;
use App\Models\Jubelioreturn;
use App\Models\Produksi;
use App\Models\RestockCell;
use App\Models\ScheduledTask;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseArrangementRefreshJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    public const QUEUE_WARN_THRESHOLD = 1;

    public const QUEUE_CRITICAL_THRESHOLD = 50;

    public const ACTIVITY_CHART_DAYS = 7;

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
        $canActivity = Gate::forUser($user)->allows('transactions-list');
        $canRestock = Gate::forUser($user)->allows('restock-list');
        $canProduksiList = Gate::forUser($user)->allows(Produksi::getPermissions()['view']);
        $canProduksiSetoran = Gate::forUser($user)->allows(Produksi::getPermissions()['setoran-view']);

        $data = [
            'can' => [
                'jubelio' => $canJubelio,
                'jubelio_stock_check' => $canStockCheck,
                'stock_alerts' => $canStockAlerts,
                'queue' => $canCron || $canJubelio,
                'cron_manager' => $canCron,
                'book_closing' => $canBookClosing,
                'warehouse_arrangement' => $canWarehouseArrangement,
                'activity' => $canActivity,
                'restock' => $canRestock,
                'produksi_list' => $canProduksiList,
                'produksi_setoran' => $canProduksiSetoran,
            ],
            'has_ops_panel' => $canJubelio
                || $canStockCheck
                || $canStockAlerts
                || $canCron
                || $canBookClosing
                || $canWarehouseArrangement,
            'has_daily_panel' => $canActivity
                || $canRestock
                || $canProduksiList
                || $canProduksiSetoran,
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

        if ($canActivity) {
            $data['activity'] = $this->activityPanel();
        }

        if ($canRestock) {
            $data['restock'] = $this->restockPanel();
        }

        if ($canProduksiList || $canProduksiSetoran) {
            $data['produksi'] = $this->produksiPanel($canProduksiList, $canProduksiSetoran);
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

    /**
     * @return array<string, mixed>
     */
    protected function activityPanel(): array
    {
        $today = now()->toDateString();
        $chartStart = now()->subDays(self::ACTIVITY_CHART_DAYS - 1)->startOfDay()->toDateString();

        $aggregates = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$chartStart, $today])
            ->whereIn('type', [
                Transaction::TYPE_BUY,
                Transaction::TYPE_SELL,
                Transaction::TYPE_CASH_IN,
                Transaction::TYPE_CASH_OUT,
            ])
            ->selectRaw('date, type, COUNT(*) as row_count, SUM(ABS(COALESCE(real_total, total, 0))) as amount_total')
            ->groupBy('date', 'type')
            ->get();

        $byDate = $aggregates->groupBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $todayRows = $byDate->get($today, collect());
        $todayStats = $this->aggregateActivityStats($todayRows);

        $chartDays = collect(range(0, self::ACTIVITY_CHART_DAYS - 1))
            ->map(function (int $offset) use ($chartStart) {
                $date = Carbon::parse($chartStart)->addDays($offset);
                $dateString = $date->toDateString();

                return [
                    'date' => $dateString,
                    'label' => $date->translatedFormat('D d'),
                    'stats' => ['sell_count' => 0, 'buy_count' => 0, 'sell_total' => 0.0, 'buy_total' => 0.0],
                ];
            })
            ->keyBy('date');

        foreach ($byDate as $dateString => $rows) {
            if (! $chartDays->has($dateString)) {
                continue;
            }

            $day = $chartDays->get($dateString);
            $day['stats'] = $this->aggregateActivityStats($rows);
            $chartDays->put($dateString, $day);
        }

        $maxSellTotal = max(1.0, (float) $chartDays->max('stats.sell_total'));

        return [
            'today' => $todayStats,
            'chart' => $chartDays
                ->values()
                ->map(function (array $day) use ($maxSellTotal) {
                    $day['sell_count'] = $day['stats']['sell_count'];
                    $day['buy_count'] = $day['stats']['buy_count'];
                    $day['sell_total'] = $day['stats']['sell_total'];
                    $day['buy_total'] = $day['stats']['buy_total'];
                    $day['bar_percent'] = (int) round(($day['sell_total'] / $maxSellTotal) * 100);
                    unset($day['stats']);

                    return $day;
                })
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{sell_count: int, buy_count: int, cash_in_total: float, cash_out_total: float, sell_total: float, buy_total: float}
     */
    protected function aggregateActivityStats(Collection $rows): array
    {
        $stats = [
            'sell_count' => 0,
            'buy_count' => 0,
            'cash_in_total' => 0.0,
            'cash_out_total' => 0.0,
            'sell_total' => 0.0,
            'buy_total' => 0.0,
        ];

        foreach ($rows as $row) {
            $type = (int) $row->type;
            $count = (int) $row->row_count;
            $amount = (float) $row->amount_total;

            match ($type) {
                Transaction::TYPE_SELL => [
                    $stats['sell_count'] += $count,
                    $stats['sell_total'] += $amount,
                ],
                Transaction::TYPE_BUY => [
                    $stats['buy_count'] += $count,
                    $stats['buy_total'] += $amount,
                ],
                Transaction::TYPE_CASH_IN => $stats['cash_in_total'] += $amount,
                Transaction::TYPE_CASH_OUT => $stats['cash_out_total'] += $amount,
                default => null,
            };
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    protected function restockPanel(): array
    {
        $urgentCells = RestockCell::query()
            ->where('is_urgent', true)
            ->with(['item:id,code,name', 'sheet:id,name,type_tag_id', 'sheet.typeTag:id,name'])
            ->orderByDesc('urgent_flagged_at')
            ->orderByDesc('updated_at');

        return [
            'urgent_count' => (int) (clone $urgentCells)->count(),
            'recent' => (clone $urgentCells)->limit(5)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function produksiPanel(bool $canList, bool $canSetoran): array
    {
        $recentFrom = now()->subDays(self::ACTIVITY_CHART_DAYS - 1)->startOfDay()->toDateString();
        $data = ['recent_days' => self::ACTIVITY_CHART_DAYS];

        if ($canList) {
            $data['pending_produksi'] = (int) Produksi::query()
                ->where('status', Produksi::STATUS_PRODUKSI)
                ->count();
        }

        if ($canSetoran) {
            $data['pending_setoran'] = (int) Produksi::query()
                ->where('status', Produksi::STATUS_SETOR)
                ->whereDate('potong_date', '>=', $recentFrom)
                ->count();
        }

        return $data;
    }
}
