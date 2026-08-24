@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $can = $dashboard['can'] ?? [];
    $jubelio = $dashboard['jubelio'] ?? null;
    $stockAlerts = $dashboard['stock_alerts'] ?? null;
    $queue = $dashboard['queue'] ?? null;
    $hasOpsPanel = $dashboard['has_ops_panel'] ?? false;
    $fmtNum = fn ($v) => number_format((int) $v, 0, ',', '.');
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
