@extends('layouts.app')

@section('title', 'Data Retention')

@section('content')
@php
use App\Models\DataRetentionRun;

$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Data Retention', 'href' => route('data-retention.index')],
];

$archivePreview = session('archive_preview');
$cleanupPreview = session('cleanup_preview');

$statusStyles = [
    DataRetentionRun::STATUS_PENDING => 'bg-gray-100 text-gray-700',
    DataRetentionRun::STATUS_COPYING => 'bg-blue-100 text-blue-800',
    DataRetentionRun::STATUS_ARCHIVED => 'bg-amber-100 text-amber-800',
    DataRetentionRun::STATUS_CLEANING => 'bg-orange-100 text-orange-800',
    DataRetentionRun::STATUS_CLEANED => 'bg-emerald-100 text-emerald-800',
    DataRetentionRun::STATUS_FAILED => 'bg-red-100 text-red-800',
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Data Retention</h1>
        <p class="text-gray-500 dark:text-gray-400">
            Copy whole calendar years to the archive database, then remove them from live with confirmation.
            Initial bootstrap: mysqldump live → import as <code class="rounded bg-gray-100 px-1">aria_archive</code> → delete recent-year rows on the archive copy.
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap gap-4 text-sm">
            <div>Archive DB: <strong class="{{ $archiveConfigured ? 'text-emerald-700' : 'text-red-700' }}">{{ $archiveConfigured ? 'connected' : 'not connected' }}</strong></div>
            <div>Retention: <strong>{{ $retentionYears }}</strong> full year(s)</div>
            <div>Live keeps from year: <strong>{{ $liveStartYear }}</strong></div>
            <div>Partition drop: <strong>{{ $usesPartitioning ? 'yes' : 'no (row delete)' }}</strong></div>
        </div>
        @if($eligibleYears !== [])
        <p class="mt-3 text-sm text-gray-600">Eligible for archive/cleanup: <strong>{{ implode(', ', $eligibleYears) }}</strong></p>
        @endif
    </div>

    @if($archivePreview)
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm">
        <div class="font-semibold text-blue-900">Archive preview — year {{ $archivePreview['year'] }}</div>
        <ul class="mt-2 grid gap-1 sm:grid-cols-2">
            @foreach(collect($archivePreview)->except('year') as $key => $count)
            <li><span class="text-blue-800">{{ str_replace('_', ' ', $key) }}:</span> <strong>{{ number_format($count) }}</strong></li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($cleanupPreview)
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
        <div class="font-semibold text-amber-900">Live cleanup preview — year {{ $cleanupPreview['year'] }}</div>
        <ul class="mt-2 grid gap-1 sm:grid-cols-2">
            @foreach(collect($cleanupPreview)->except('year', 'uses_partition_drop') as $key => $count)
            <li><span class="text-amber-900">{{ str_replace('_', ' ', $key) }}:</span> <strong>{{ number_format($count) }}</strong></li>
            @endforeach
        </ul>
        <p class="mt-2 text-amber-800">Partition drop: {{ ($cleanupPreview['uses_partition_drop'] ?? false) ? 'yes' : 'no' }}</p>
    </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold">1. Copy year → archive</h2>
            <p class="mt-1 text-sm text-gray-500">Idempotent insert into the archive database. Run once per year before live cleanup.</p>
            <form method="POST" action="{{ route('data-retention.preview-archive') }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Year</label>
                    <input type="number" name="year" required min="2000" max="2100" value="{{ $eligibleYears[0] ?? '' }}" class="h-9 w-28 rounded-md border border-gray-300 px-3 text-sm">
                </div>
                <button type="submit" class="h-9 rounded-md border border-gray-300 bg-white px-4 text-sm font-medium hover:bg-gray-50">Preview</button>
            </form>
            <form method="POST" action="{{ route('data-retention.archive-year') }}" class="mt-4 space-y-2 border-t pt-4"
                  onsubmit="return confirm('Copy this year to the archive database?');">
                @csrf
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Year</label>
                        <input type="number" name="year" required min="2000" max="2100" value="{{ $eligibleYears[0] ?? '' }}" class="h-9 w-28 rounded-md border border-gray-300 px-3 text-sm">
                    </div>
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-xs font-medium text-gray-500">Type ARCHIVE-YYYY to confirm</label>
                        <input type="text" name="confirm" required placeholder="ARCHIVE-2020" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm font-mono">
                    </div>
                </div>
                <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700" @disabled(! $archiveConfigured)>Copy to archive</button>
            </form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold">2. Remove year from live</h2>
            <p class="mt-1 text-sm text-gray-500">Requires the year to be archived first. Drops the yearly partition when available.</p>
            <form method="POST" action="{{ route('data-retention.preview-cleanup') }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Year</label>
                    <input type="number" name="year" required min="2000" max="2100" value="{{ $eligibleYears[0] ?? '' }}" class="h-9 w-28 rounded-md border border-gray-300 px-3 text-sm">
                </div>
                <button type="submit" class="h-9 rounded-md border border-gray-300 bg-white px-4 text-sm font-medium hover:bg-gray-50">Preview</button>
            </form>
            <form method="POST" action="{{ route('data-retention.cleanup-year') }}" class="mt-4 space-y-2 border-t pt-4"
                  onsubmit="return confirm('PERMANENTLY remove this year from the LIVE database?');">
                @csrf
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Year</label>
                        <input type="number" name="year" required min="2000" max="2100" value="{{ $eligibleYears[0] ?? '' }}" class="h-9 w-28 rounded-md border border-gray-300 px-3 text-sm">
                    </div>
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-xs font-medium text-gray-500">Type CLEANUP-YYYY to confirm</label>
                        <input type="text" name="confirm" required placeholder="CLEANUP-2020" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm font-mono">
                    </div>
                </div>
                <button type="submit" class="h-9 rounded-md bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700">Remove from live</button>
            </form>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold">3. Purge orphans (live)</h2>
        <p class="mt-1 text-sm text-gray-500">
            Contacts/items created before <strong>{{ $orphanPreview['cutoff_year'] }}</strong> with no remaining transaction references.
            Soft-deleted rows are included. Warehouse stock blocks the standard item purge only.
        </p>

        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-gray-500">Orphan items (no stock)</dt><dd class="font-semibold tabular-nums">{{ number_format($orphanPreview['items']) }}</dd></div>
            <div><dt class="text-gray-500">Orphan items (incl. stock)</dt><dd class="font-semibold tabular-nums">{{ number_format($orphanPreview['items_with_stock']) }}</dd></div>
            <div><dt class="text-gray-500">Orphan item groups</dt><dd class="font-semibold tabular-nums">{{ number_format($orphanPreview['item_groups']) }}</dd></div>
            <div><dt class="text-gray-500">Orphan customers</dt><dd class="font-semibold tabular-nums">{{ number_format($orphanPreview['customers']) }}</dd></div>
            <div><dt class="text-gray-500">Orphan suppliers</dt><dd class="font-semibold tabular-nums">{{ number_format($orphanPreview['suppliers']) }}</dd></div>
            <div><dt class="text-gray-500">Orphan resellers</dt><dd class="font-semibold tabular-nums">{{ number_format($orphanPreview['resellers']) }}</dd></div>
        </dl>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <form method="POST" action="{{ route('data-retention.purge-orphan-items') }}" class="space-y-2 rounded-lg border border-gray-200 p-3"
                  onsubmit="return confirm('Permanently delete orphan items (zero warehouse stock)?');">
                @csrf
                <div class="text-sm font-medium">Items (standard)</div>
                <input type="text" name="confirm" required placeholder="PURGE-ORPHAN-ITEMS" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm font-mono">
                <button type="submit" class="h-9 rounded-md border border-red-300 bg-red-50 px-4 text-sm font-medium text-red-800 hover:bg-red-100">Purge orphan items</button>
            </form>

            <form method="POST" action="{{ route('data-retention.purge-orphan-item-groups') }}" class="space-y-2 rounded-lg border border-gray-200 p-3"
                  onsubmit="return confirm('Permanently delete orphan item groups?');">
                @csrf
                <div class="text-sm font-medium">Item groups (no remaining items)</div>
                <input type="text" name="confirm" required placeholder="PURGE-ORPHAN-ITEM-GROUPS" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm font-mono">
                <button type="submit" class="h-9 rounded-md border border-red-300 bg-red-50 px-4 text-sm font-medium text-red-800 hover:bg-red-100">Purge orphan item groups</button>
            </form>

            @foreach(['customers' => 'PURGE-ORPHAN-CUSTOMERS', 'suppliers' => 'PURGE-ORPHAN-SUPPLIERS', 'resellers' => 'PURGE-ORPHAN-RESELLERS'] as $slug => $token)
            <form method="POST" action="{{ route('data-retention.purge-orphan-addrbooks', $slug) }}" class="space-y-2 rounded-lg border border-gray-200 p-3"
                  onsubmit="return confirm('Permanently delete orphan {{ $slug }}?');">
                @csrf
                <div class="text-sm font-medium">{{ ucfirst($slug) }}</div>
                <input type="text" name="confirm" required placeholder="{{ $token }}" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm font-mono">
                <button type="submit" class="h-9 rounded-md border border-red-300 bg-red-50 px-4 text-sm font-medium text-red-800 hover:bg-red-100">Purge orphan {{ $slug }}</button>
            </form>
            @endforeach
        </div>

        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            To purge old items that still have warehouse stock, use the
            <a href="{{ route('data-retention.item-purge.index') }}" class="font-medium underline">selective item purge</a> page (preview + <code class="rounded bg-amber-100 px-1">PURGE-ITEMS-WITH-STOCK</code>).
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="border-b px-4 py-3 font-semibold">Year status</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Year</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Copied</th>
                        <th class="px-3 py-2 font-medium">Cleaned</th>
                        <th class="px-3 py-2 font-medium">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($runs as $run)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $run->year }}</td>
                        <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold uppercase {{ $statusStyles[$run->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $run->status }}</span></td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ $run->archive_finished_at?->format('d/m/Y H:i') ?? '—' }}<br><span class="text-gray-400">{{ number_format($run->transactions_copied) }} tx</span></td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ $run->cleanup_finished_at?->format('d/m/Y H:i') ?? '—' }}<br><span class="text-gray-400">{{ number_format($run->items_purged) }} items purged</span></td>
                        <td class="max-w-xs truncate px-3 py-2 text-xs text-red-700" title="{{ $run->last_error }}">{{ $run->last_error ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No retention runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
