@extends('layouts.app')

@section('title', 'Warehouse Stats Backfill')

@section('content')
@php
use App\Models\WarehouseStatBackfill;

$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Warehouse Stats Backfill', 'href' => route('warehouse-stat-backfill.index')],
];

$statusStyles = [
    WarehouseStatBackfill::STATUS_IDLE => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    WarehouseStatBackfill::STATUS_RUNNING => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-100',
    WarehouseStatBackfill::STATUS_PAUSED => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-100',
    WarehouseStatBackfill::STATUS_COMPLETED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-100',
    WarehouseStatBackfill::STATUS_FAILED => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-100',
];
$statusStyle = $statusStyles[$state->status] ?? $statusStyles[WarehouseStatBackfill::STATUS_IDLE];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Warehouse Stats Backfill</h1>
        <p class="text-gray-500 dark:text-gray-400">
            Rebuilds historical monthly sell/return stats a batch of months at a time, newest first.
            Recent activity is already kept current by the per-transaction updates and the daily reconcile job.
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-100">{{ $flash['success'] }}</div>
    @endif

    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusStyle }}">{{ $state->status }}</span>
                @if($nextPeriod)
                <span class="text-sm text-gray-600 dark:text-gray-300">Next month to rebuild: <strong>{{ $nextPeriod }}</strong></span>
                @endif
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $state->months_done }} / {{ $state->months_total }} month(s) · {{ $remainingMonths }} remaining
            </span>
        </div>

        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $state->progressPercent() }}%"></div>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $state->progressPercent() }}% complete · {{ number_format($state->rows_written) }} stat row(s) written</p>

        @if($state->last_run_at)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Last batch finished: {{ $state->last_run_at->format('Y-m-d H:i:s') }} ({{ $state->last_run_at->diffForHumans() }})</p>
        @elseif($state->isRunning() && $state->months_done > 0)
        <p class="mt-1 text-xs text-amber-700 dark:text-amber-200">No batch has finished since the last restart — use <strong>Run batch now</strong> or check Cron Manager.</p>
        @endif

        @if($batchStale ?? false)
        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100">
            Progress has not moved in over 15 minutes. The automatic worker may be stuck or not running — click <strong>Run batch now</strong> (try <strong>1</strong> month), or run
            <code class="text-xs">php artisan app:backfill-warehouse-item-stats --months=1</code> on the server. Check Cron Manager that
            <code class="text-xs">app:backfill-warehouse-item-stats --months=1 --max-seconds=50</code> is active.
        </div>
        @endif

        @if($state->last_error)
        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">
            Last error: {{ $state->last_error }}
        </div>
        @endif

        <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
            @if($state->status !== WarehouseStatBackfill::STATUS_RUNNING)
            <form method="POST" action="{{ route('warehouse-stat-backfill.start') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label for="since" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Oldest month to rebuild</label>
                    <input id="since" name="since" type="date" value="{{ $defaultSince }}"
                           class="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </div>
                <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                    {{ $state->status === WarehouseStatBackfill::STATUS_IDLE ? 'Start backfill' : 'Restart from newest' }}
                </button>
            </form>
            @endif

            @if($state->status === WarehouseStatBackfill::STATUS_PAUSED)
            <form method="POST" action="{{ route('warehouse-stat-backfill.resume') }}">
                @csrf
                <button type="submit" class="h-9 rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    Resume
                </button>
            </form>
            @endif

            @if($state->status === WarehouseStatBackfill::STATUS_RUNNING)
            <form method="POST" action="{{ route('warehouse-stat-backfill.run-batch') }}" class="flex items-end gap-2">
                @csrf
                <div>
                    <label for="months" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Months this batch</label>
                    <input id="months" name="months" type="number" min="1" max="{{ $maxManualBatch }}" value="{{ $defaultBatch }}"
                           class="h-9 w-28 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </div>
                <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                    Run batch now
                </button>
            </form>

            <form method="POST" action="{{ route('warehouse-stat-backfill.pause') }}">
                @csrf
                <button type="submit" class="h-9 rounded-md border border-amber-300 bg-amber-50 px-4 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100 dark:hover:bg-amber-900/60">
                    Pause
                </button>
            </form>

            <form method="POST" action="{{ route('warehouse-stat-backfill.start') }}" class="flex items-end gap-2">
                @csrf
                <input type="hidden" name="since" value="{{ $defaultSince }}">
                <button type="submit" class="h-9 rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    Restart from newest
                </button>
            </form>
            @endif
        </div>

        @if($state->status === WarehouseStatBackfill::STATUS_RUNNING)
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            The cron runs <code>app:backfill-warehouse-item-stats --months=1 --max-seconds=50</code> every five minutes (one month per tick). Use <strong>Run batch now</strong> to move faster.
        </p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Transaction history</h2>
            <dl class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Earliest sell/return</dt>
                    <dd>{{ $historyStart ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Latest sell/return</dt>
                    <dd>{{ $historyEnd ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Current stats coverage</h2>
            <dl class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Stat rows</dt>
                    <dd>{{ number_format($coverage['rows']) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Months covered</dt>
                    <dd>{{ $coverage['periods'] }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Range</dt>
                    <dd>{{ $coverage['earliest'] ? $coverage['earliest'].' → '.$coverage['latest'] : '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">
        <h2 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">How stats stay current</h2>
        <ul class="list-inside list-disc space-y-1">
            <li>Each completed sell/return updates its month immediately through the queued transaction summary job.</li>
            <li>The daily reconcile job recomputes the last two months from transaction details, correcting any drift from edits or deletes.</li>
            <li>This backfill rebuilds everything older, one batch at a time, so no single run has to hold the whole history in memory.</li>
        </ul>
    </div>
</div>
@endsection
