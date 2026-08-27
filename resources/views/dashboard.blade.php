@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $can = $dashboard['can'] ?? [];
    $jubelio = $dashboard['jubelio'] ?? null;
    $jubelioStockCheck = $dashboard['jubelio_stock_check'] ?? null;
    $stockAlerts = $dashboard['stock_alerts'] ?? null;
    $queue = $dashboard['queue'] ?? null;
    $cron = $dashboard['cron'] ?? null;
    $bookClosing = $dashboard['book_closing'] ?? null;
    $warehouseArrangement = $dashboard['warehouse_arrangement'] ?? null;
    $hasOpsPanel = $dashboard['has_ops_panel'] ?? false;
    $activity = $dashboard['activity'] ?? null;
    $restock = $dashboard['restock'] ?? null;
    $produksi = $dashboard['produksi'] ?? null;
    $hasDailyPanel = $dashboard['has_daily_panel'] ?? false;
    $fmtNum = fn ($v) => format_amount($v, 0);
    $fmtMoney = fn ($v) => format_amount($v, 0);
@endphp

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard</h2>
        <p class="mt-0.5 text-sm text-gray-500">Welcome back, {{ auth()->user()->name }}.</p>
    </div>

    @if($hasOpsPanel)
    {{-- System health strip --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm" data-testid="dashboard-health-strip">
        @if(($can['jubelio'] ?? false) && $jubelio)
        @php
            $connection = $jubelio['connection'];
            $connectionState = $jubelio['connection_state'];
            $connectionStyles = match ($connectionState) {
                'ok' => ['dot' => 'bg-green-500', 'text' => 'text-green-800', 'bg' => 'bg-green-50 border-green-200'],
                'inactive' => ['dot' => 'bg-gray-400', 'text' => 'text-gray-700', 'bg' => 'bg-gray-50 border-gray-200'],
                default => ['dot' => 'bg-red-500', 'text' => 'text-red-800', 'bg' => 'bg-red-50 border-red-200'],
            };
            $connectionLabel = match ($connectionState) {
                'ok' => 'Jubelio connected',
                'inactive' => 'Jubelio inactive',
                'unconfigured' => 'Jubelio not configured',
                'no_token' => 'Jubelio no token',
                'expired' => 'Jubelio token expired',
                'api_failed' => 'Jubelio API check failed',
                default => 'Jubelio unknown',
            };
        @endphp
        <a href="{{ route('jubelio.token.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors hover:opacity-90 {{ $connectionStyles['bg'] }} {{ $connectionStyles['text'] }}"
           data-testid="dashboard-jubelio-connection">
            <span class="h-2 w-2 rounded-full {{ $connectionStyles['dot'] }}"></span>
            {{ $connectionLabel }}
            @if($connectionState === 'ok' && ($connection['expires_in_minutes'] ?? null) !== null)
            <span class="text-xs opacity-70">({{ $connection['expires_in_minutes'] }}m left)</span>
            @endif
        </a>
        @endif

        @if($queue)
        @php
            $queueStyles = match ($queue['level']) {
                'ok' => ['dot' => 'bg-green-500', 'text' => 'text-green-800', 'bg' => 'bg-green-50 border-green-200'],
                'warning' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-900', 'bg' => 'bg-amber-50 border-amber-200'],
                default => ['dot' => 'bg-red-500', 'text' => 'text-red-800', 'bg' => 'bg-red-50 border-red-200'],
            };
            $queueLabel = match (true) {
                ($queue['process_queue_active'] ?? true) === false => 'Queue worker disabled',
                $queue['pending_jobs'] === 0 => 'Queue idle',
                $queue['pending_jobs'] === 1 => '1 job pending',
                default => $fmtNum($queue['pending_jobs']).' jobs pending',
            };
            $queueHref = ($can['cron_manager'] ?? false) ? route('scheduled-tasks.index') : null;
        @endphp
        @if($queueHref)
        <a href="{{ $queueHref }}"
           class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors hover:opacity-90 {{ $queueStyles['bg'] }} {{ $queueStyles['text'] }}"
           data-testid="dashboard-queue-status">
            <span class="h-2 w-2 rounded-full {{ $queueStyles['dot'] }}"></span>
            {{ $queueLabel }}
        </a>
        @else
        <span class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium {{ $queueStyles['bg'] }} {{ $queueStyles['text'] }}"
              data-testid="dashboard-queue-status">
            <span class="h-2 w-2 rounded-full {{ $queueStyles['dot'] }}"></span>
            {{ $queueLabel }}
        </span>
        @endif
        @endif

        @if(($can['jubelio'] ?? false) && $jubelio && $jubelio['running_import'])
        @php $import = $jubelio['running_import']; @endphp
        <a href="{{ route('jubelio.get-orders.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 transition-colors hover:opacity-90"
           data-testid="dashboard-jubelio-import">
            <span class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></span>
            Get Orders import running ({{ $import->progressPercent() }}%)
        </a>
        @endif

        @if(($can['jubelio_stock_check'] ?? false) && $jubelioStockCheck && $jubelioStockCheck['active_job'])
        @php $stockCheckJob = $jubelioStockCheck['active_job']; @endphp
        <a href="{{ route('jubelio-stock-checks.show', $stockCheckJob->id) }}"
           class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 transition-colors hover:opacity-90"
           data-testid="dashboard-stock-check-active">
            <span class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></span>
            Stock check running (#{{ $stockCheckJob->id }})
        </a>
        @endif

        @if(($can['warehouse_arrangement'] ?? false) && $warehouseArrangement && $warehouseArrangement['active_count'] > 0)
        <a href="{{ route('reports.warehouse-arrangement') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-800 transition-colors hover:opacity-90"
           data-testid="dashboard-arrangement-refresh">
            <span class="h-2 w-2 animate-pulse rounded-full bg-violet-500"></span>
            {{ $warehouseArrangement['active_count'] === 1 ? 'Arrangement refresh running' : $warehouseArrangement['active_count'].' arrangement refreshes running' }}
        </a>
        @endif

        @if(($can['cron_manager'] ?? false) && $cron && $cron['disabled_count'] > 0)
        <a href="{{ route('scheduled-tasks.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-900 transition-colors hover:opacity-90"
           data-testid="dashboard-cron-disabled">
            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
            {{ $cron['disabled_count'] === 1 ? '1 cron disabled' : $fmtNum($cron['disabled_count']).' crons disabled' }}
        </a>
        @endif

        @if(($can['book_closing'] ?? false) && $bookClosing)
        @php
            $closingUrgent = ! $bookClosing['current_month_closed'] && $bookClosing['days_until_closing'] <= 3;
            $closingStyles = $bookClosing['current_month_closed']
                ? ['dot' => 'bg-gray-400', 'text' => 'text-gray-700', 'bg' => 'bg-gray-50 border-gray-200']
                : ($closingUrgent
                    ? ['dot' => 'bg-amber-500', 'text' => 'text-amber-900', 'bg' => 'bg-amber-50 border-amber-200']
                    : ['dot' => 'bg-green-500', 'text' => 'text-green-800', 'bg' => 'bg-green-50 border-green-200']);
            $closingLabel = $bookClosing['current_month_closed']
                ? 'Current month closed'
                : ($bookClosing['days_until_closing'] === 0
                    ? 'Book closing today'
                    : 'Book closing in '.$bookClosing['days_until_closing'].' day'.($bookClosing['days_until_closing'] === 1 ? '' : 's'));
        @endphp
        <span class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium {{ $closingStyles['bg'] }} {{ $closingStyles['text'] }}"
              data-testid="dashboard-book-closing">
            <span class="h-2 w-2 rounded-full {{ $closingStyles['dot'] }}"></span>
            {{ $closingLabel }}
        </span>
        @endif
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if(($can['jubelio'] ?? false) && $jubelio)
        <a href="{{ route('jubelio.index', ['status' => 'pending']) }}"
           class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm transition-all hover:shadow-md"
           data-testid="dashboard-kpi-jubelio-pending">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700/80">Pending Jubelio Orders</p>
            <p class="mt-2 text-3xl font-bold text-blue-900">{{ $fmtNum($jubelio['order_stats']['pending']) }}</p>
            <p class="mt-1 text-sm text-blue-700/80">Awaiting sync to Aria</p>
        </a>

        <a href="{{ route('jubelio.index', ['status' => 'error']) }}"
           class="rounded-xl border p-5 shadow-sm transition-all hover:shadow-md {{ $jubelio['order_stats']['error'] > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }}"
           data-testid="dashboard-kpi-jubelio-error">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $jubelio['order_stats']['error'] > 0 ? 'text-red-700/80' : 'text-gray-500' }}">Error SKU</p>
            <p class="mt-2 text-3xl font-bold {{ $jubelio['order_stats']['error'] > 0 ? 'text-red-900' : 'text-gray-900' }}">{{ $fmtNum($jubelio['order_stats']['error']) }}</p>
            <p class="mt-1 text-sm {{ $jubelio['order_stats']['error'] > 0 ? 'text-red-700/80' : 'text-gray-500' }}">Orders needing attention</p>
        </a>
        @endif

        @if(($can['stock_alerts'] ?? false) && $stockAlerts)
        <a href="{{ route('stock-notifications.index') }}"
           class="rounded-xl border p-5 shadow-sm transition-all hover:shadow-md {{ $stockAlerts['unread_count'] > 0 ? 'border-rose-200 bg-rose-50' : 'border-gray-200 bg-white' }}"
           data-testid="dashboard-kpi-stock-alerts">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $stockAlerts['unread_count'] > 0 ? 'text-rose-700/80' : 'text-gray-500' }}">Stock Alerts</p>
            <p class="mt-2 text-3xl font-bold {{ $stockAlerts['unread_count'] > 0 ? 'text-rose-900' : 'text-gray-900' }}">{{ $fmtNum($stockAlerts['unread_count']) }}</p>
            <p class="mt-1 text-sm {{ $stockAlerts['unread_count'] > 0 ? 'text-rose-700/80' : 'text-gray-500' }}">Unread notifications</p>
        </a>
        @endif

        @if($queue)
        @if($can['cron_manager'] ?? false)
        <a href="{{ route('scheduled-tasks.index') }}"
           class="rounded-xl border p-5 shadow-sm transition-all hover:shadow-md {{ $queue['level'] !== 'ok' ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-white' }}"
           data-testid="dashboard-kpi-queue">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $queue['level'] !== 'ok' ? 'text-amber-800/80' : 'text-gray-500' }}">Queue Backlog</p>
            <p class="mt-2 text-3xl font-bold {{ $queue['level'] !== 'ok' ? 'text-amber-900' : 'text-gray-900' }}">{{ $fmtNum($queue['pending_jobs']) }}</p>
            <p class="mt-1 text-sm {{ $queue['level'] !== 'ok' ? 'text-amber-800/80' : 'text-gray-500' }}">
                @if(($queue['process_queue_active'] ?? true) === false)
                Process Queue cron is off
                @elseif($queue['pending_jobs'] === 0)
                No pending jobs
                @else
                Jobs waiting to process
                @endif
            </p>
        </a>
        @endif
        @endif

        @if(($can['jubelio'] ?? false) && $jubelio)
        <a href="{{ route('jubelio.returns.index') }}"
           class="rounded-xl border p-5 shadow-sm transition-all hover:shadow-md {{ $jubelio['pending_cancellations'] > 0 ? 'border-orange-200 bg-orange-50' : 'border-gray-200 bg-white' }}"
           data-testid="dashboard-kpi-cancellations">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $jubelio['pending_cancellations'] > 0 ? 'text-orange-800/80' : 'text-gray-500' }}">Pending Cancellations</p>
            <p class="mt-2 text-3xl font-bold {{ $jubelio['pending_cancellations'] > 0 ? 'text-orange-900' : 'text-gray-900' }}">{{ $fmtNum($jubelio['pending_cancellations']) }}</p>
            <p class="mt-1 text-sm {{ $jubelio['pending_cancellations'] > 0 ? 'text-orange-800/80' : 'text-gray-500' }}">Jubelio returns to process</p>
        </a>
        @endif

        @if(($can['jubelio_stock_check'] ?? false) && $jubelioStockCheck)
        @php $discrepancies = $jubelioStockCheck['latest_discrepancies']; @endphp
        <a href="{{ $jubelioStockCheck['latest_completed'] ? route('jubelio-stock-checks.show', $jubelioStockCheck['latest_completed']->id) : route('jubelio-stock-checks.index') }}"
           class="rounded-xl border p-5 shadow-sm transition-all hover:shadow-md {{ $discrepancies > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }}"
           data-testid="dashboard-kpi-stock-discrepancies">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $discrepancies > 0 ? 'text-red-700/80' : 'text-gray-500' }}">Stock Discrepancies</p>
            <p class="mt-2 text-3xl font-bold {{ $discrepancies > 0 ? 'text-red-900' : 'text-gray-900' }}">{{ $fmtNum($discrepancies) }}</p>
            <p class="mt-1 text-sm {{ $discrepancies > 0 ? 'text-red-700/80' : 'text-gray-500' }}">
                @if($jubelioStockCheck['latest_completed'])
                Latest check #{{ $jubelioStockCheck['latest_completed']->id }}
                @else
                No completed checks yet
                @endif
            </p>
        </a>
        @endif

        @if(($can['cron_manager'] ?? false) && $cron)
        <a href="{{ route('scheduled-tasks.index') }}"
           class="rounded-xl border p-5 shadow-sm transition-all hover:shadow-md {{ $cron['disabled_count'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-white' }}"
           data-testid="dashboard-kpi-crons-disabled">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $cron['disabled_count'] > 0 ? 'text-amber-800/80' : 'text-gray-500' }}">Disabled Crons</p>
            <p class="mt-2 text-3xl font-bold {{ $cron['disabled_count'] > 0 ? 'text-amber-900' : 'text-gray-900' }}">{{ $fmtNum($cron['disabled_count']) }}</p>
            <p class="mt-1 text-sm {{ $cron['disabled_count'] > 0 ? 'text-amber-800/80' : 'text-gray-500' }}">of {{ $fmtNum($cron['total_tasks']) }} scheduled tasks</p>
        </a>
        @endif

        @if(($can['book_closing'] ?? false) && $bookClosing)
        @php
            $closingCardUrgent = ! $bookClosing['current_month_closed'] && $bookClosing['days_until_closing'] <= 3;
        @endphp
        <div class="rounded-xl border p-5 shadow-sm {{ $bookClosing['current_month_closed'] ? 'border-gray-200 bg-gray-50' : ($closingCardUrgent ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-white') }}"
             data-testid="dashboard-kpi-book-closing">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $closingCardUrgent ? 'text-amber-800/80' : 'text-gray-500' }}">Book Closing</p>
            <p class="mt-2 text-3xl font-bold {{ $closingCardUrgent ? 'text-amber-900' : 'text-gray-900' }}">
                @if($bookClosing['current_month_closed'])
                Closed
                @elseif($bookClosing['days_until_closing'] === 0)
                Today
                @else
                {{ $fmtNum($bookClosing['days_until_closing']) }}d
                @endif
            </p>
            <p class="mt-1 text-sm {{ $closingCardUrgent ? 'text-amber-800/80' : 'text-gray-500' }}">
                Tutup buku akhir bulan · {{ $bookClosing['closing_date']->translatedFormat('d M Y') }}
                · entri dari {{ $bookClosing['min_allowed_date']->translatedFormat('d M Y') }}
            </p>
        </div>
        @endif
    </div>

    @if(($can['stock_alerts'] ?? false) && $stockAlerts)
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="dashboard-stock-alerts-list">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Recent Stock Alerts</h3>
                <p class="text-xs text-gray-500">Sold out at one warehouse while stock remains elsewhere</p>
            </div>
            <a href="{{ route('stock-notifications.index') }}" class="text-sm font-medium text-blue-700 hover:underline">View all</a>
        </div>

        @if($stockAlerts['recent']->isEmpty())
        <div class="px-5 py-8 text-center text-sm text-gray-500">No unread stock alerts.</div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Item</th>
                        <th class="px-5 py-3">Sold out at</th>
                        <th class="px-5 py-3">Stock at</th>
                        <th class="px-5 py-3">Qty</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stockAlerts['recent'] as $notification)
                    <tr class="bg-blue-50/40">
                        <td class="px-5 py-3">
                            <div class="font-medium text-gray-900">{{ $notification->item?->code }}</div>
                            <div class="text-xs text-gray-500">{{ $notification->item?->name }}</div>
                        </td>
                        <td class="px-5 py-3 text-gray-700">{{ $notification->soldOutWarehouse?->name }}</td>
                        <td class="px-5 py-3 text-gray-700">{{ $notification->sourceWarehouse?->name }}</td>
                        <td class="px-5 py-3 font-mono text-gray-700">{{ $fmtNum($notification->source_stock) }}</td>
                        <td class="px-5 py-3">
                            @if($notification->source_status)
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $notification->source_status->colorClass() }}">
                                {{ $notification->source_status->label() }}
                            </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $notification->created_at?->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif

    @if(($can['warehouse_arrangement'] ?? false) && $warehouseArrangement && $warehouseArrangement['active_jobs']->isNotEmpty())
    <div class="rounded-xl border border-violet-200 bg-violet-50/50 shadow-sm" data-testid="dashboard-arrangement-jobs">
        <div class="border-b border-violet-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-violet-900">Warehouse Arrangement Refresh</h3>
            <p class="text-xs text-violet-700/80">Background rebuild jobs in progress</p>
        </div>
        <ul class="divide-y divide-violet-100 px-5 py-2 text-sm">
            @foreach($warehouseArrangement['active_jobs'] as $refreshJob)
            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                <div>
                    <p class="font-medium text-gray-900">{{ $refreshJob->destinationWarehouse?->name ?? 'Warehouse #'.$refreshJob->destination_warehouse_id }}</p>
                    <p class="text-xs text-gray-500">{{ $refreshJob->phase }} · {{ $refreshJob->initiatedByLabel() }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-violet-800">{{ $refreshJob->progressPercent() }}%</span>
                    <a href="{{ route('reports.warehouse-arrangement', ['warehouse_id' => $refreshJob->destination_warehouse_id]) }}"
                       class="text-xs font-medium text-blue-700 hover:underline">View</a>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(($can['cron_manager'] ?? false) && $cron && $cron['disabled_count'] > 0)
    <div class="rounded-xl border border-amber-200 bg-amber-50/50 shadow-sm" data-testid="dashboard-disabled-crons-list">
        <div class="flex items-center justify-between border-b border-amber-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-amber-900">Disabled Scheduled Tasks</h3>
                <p class="text-xs text-amber-800/80">These crons will not run until re-enabled</p>
            </div>
            <a href="{{ route('scheduled-tasks.index') }}" class="text-sm font-medium text-blue-700 hover:underline">Cron Manager</a>
        </div>
        <ul class="divide-y divide-amber-100 px-5 py-2 text-sm">
            @foreach($cron['disabled_tasks'] as $task)
            <li class="py-2.5">
                <p class="font-medium text-gray-900">{{ $task->name }}</p>
                <p class="font-mono text-xs text-gray-500">{{ $task->command }}</p>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
    @endif

    @if($hasDailyPanel)
    <div class="flex flex-col gap-4" data-testid="dashboard-daily-panel">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Daily checklist</h3>
            <p class="text-sm text-gray-500">Items staff should review and action today</p>
        </div>

        @if(($can['activity'] ?? false) && $activity)
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="dashboard-activity-chart">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Transaction Activity</h4>
                    <p class="text-xs text-gray-500">Sell volume over the last {{ count($activity['chart']) }} days</p>
                </div>
                <a href="{{ route('transactions.index') }}" class="text-sm font-medium text-blue-700 hover:underline">All transactions</a>
            </div>

            <div class="grid grid-cols-2 gap-3 border-b border-gray-100 px-5 py-4 sm:grid-cols-4">
                <div>
                    <p class="text-xs uppercase text-gray-500">Today sell</p>
                    <p class="text-lg font-bold text-gray-900">{{ $fmtNum($activity['today']['sell_count']) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500">Today buy</p>
                    <p class="text-lg font-bold text-gray-900">{{ $fmtNum($activity['today']['buy_count']) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500">Sell total</p>
                    <p class="text-lg font-bold text-blue-900">{{ $fmtMoney($activity['today']['sell_total']) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500">Buy total</p>
                    <p class="text-lg font-bold text-green-900">{{ $fmtMoney($activity['today']['buy_total']) }}</p>
                </div>
            </div>

            <div class="flex items-end gap-2 px-5 py-6">
                @foreach($activity['chart'] as $day)
                <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <span class="text-[10px] font-medium text-gray-500">{{ $fmtMoney($day['sell_total']) }}</span>
                    <div class="flex h-28 w-full items-end justify-center rounded-md bg-gray-50 px-1">
                        <div class="w-full max-w-[2rem] rounded-t bg-blue-500 transition-all"
                             style="height: {{ max($day['bar_percent'], $day['sell_count'] > 0 ? 8 : 0) }}%"
                             title="{{ $day['label'] }}: {{ $fmtNum($day['sell_count']) }} sells"></div>
                    </div>
                    <span class="truncate text-[10px] text-gray-500">{{ $day['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @if(($can['restock'] ?? false) && $restock)
            <div class="rounded-xl border p-5 shadow-sm {{ $restock['urgent_count'] > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-gray-200 bg-white' }}"
                 data-testid="dashboard-restock-urgent">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900">Urgent restock</h4>
                        <p class="text-xs text-gray-500">Asset lancar SKUs flagged on restock sheets</p>
                    </div>
                    <a href="{{ route('restock.index') }}" class="text-sm font-medium text-blue-700 hover:underline">Restock</a>
                </div>
                <p class="mt-3 text-3xl font-bold {{ $restock['urgent_count'] > 0 ? 'text-rose-900' : 'text-gray-900' }}">
                    {{ $fmtNum($restock['urgent_count']) }}
                </p>
                @if($restock['recent']->isNotEmpty())
                <ul class="mt-4 divide-y divide-rose-100 rounded-lg border border-rose-100 bg-white/80 text-sm">
                    @foreach($restock['recent'] as $cell)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                        <div>
                            <p class="font-medium text-gray-900">{{ $cell->item?->code }}</p>
                            <p class="text-xs text-gray-500">{{ $cell->item?->name }} · {{ $cell->sheet?->typeTag?->name ?? $cell->sheet?->name }}</p>
                        </div>
                        <span class="text-xs font-medium text-rose-700">restock {{ $fmtNum($cell->qty_restock) }}</span>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="mt-3 text-sm text-gray-500">No urgent restock items.</p>
                @endif
            </div>
            @endif

            @if(($can['produksi_list'] ?? false) && $produksi)
            <div class="rounded-xl border p-5 shadow-sm {{ ($produksi['recent_produksi_count'] ?? 0) > 0 ? 'border-indigo-200 bg-indigo-50/40' : 'border-gray-200 bg-white' }}"
                 data-testid="dashboard-produksi-recent">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900">In production</h4>
                        <p class="text-xs text-gray-500">Potong jobs from the last {{ $produksi['recent_days'] ?? 7 }} days</p>
                    </div>
                    <a href="{{ route('produksi.index') }}" class="text-sm font-medium text-blue-700 hover:underline">Production</a>
                </div>
                <p class="mt-3 text-3xl font-bold {{ ($produksi['recent_produksi_count'] ?? 0) > 0 ? 'text-indigo-900' : 'text-gray-900' }}">
                    {{ $fmtNum($produksi['recent_produksi_count'] ?? 0) }}
                </p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Quick-links grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('transactions.index') }}"
           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-blue-300 hover:shadow-md transition-all">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Transactions</p>
                <p class="text-sm text-gray-500">View all records</p>
            </div>
        </a>
        <a href="{{ route('items.index') }}"
           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-green-300 hover:shadow-md transition-all">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Items</p>
                <p class="text-sm text-gray-500">Manage inventory</p>
            </div>
        </a>
        <a href="{{ route('addrbook.type.index', 'customer') }}"
           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-purple-300 hover:shadow-md transition-all">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
                <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Contacts</p>
                <p class="text-sm text-gray-500">Address book</p>
            </div>
        </a>
    </div>
</div>
@endsection
