@extends('layouts.app')

@section('title', 'Omnichannel — Missed push')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.index')],
    ['title' => 'Missed push', 'href' => route('omnichannel.missed-push.index')],
];
$th = 'px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500';
$td = 'px-2 py-2 align-top text-xs text-gray-800';
$hasActiveFilters = collect(request()->query())
    ->only(['channel', 'date_from', 'date_to', 'order_ref', 'shop_ref', 'per_page'])
    ->filter(fn ($value) => $value !== null && $value !== '')
    ->isNotEmpty();
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4" data-testid="omnichannel-missed-push-page">
    <div class="max-w-4xl">
        <h1 class="text-2xl font-bold text-gray-900">Missed push (reconcile)</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-600">
            Platform punya order, tetapi belum ada baris di
            <code class="rounded bg-gray-100 px-1 text-xs">marketplace_channel_orders</code>
            — antrian reconcile (Shopee MVP). TikTok menyusul.
        </p>
        <p class="mt-2 text-xs text-gray-500">
            <a href="{{ route('omnichannel.orders.index', ['view' => 'events']) }}" class="text-blue-700 hover:underline">Webhooks</a>
            ·
            <a href="{{ route('omnichannel.orders.index', ['view' => 'orders']) }}" class="text-blue-700 hover:underline">Current orders</a>
        </p>
    </div>

    @include('omnichannel.partials.missed-push-filters', [
        'filters' => $filters,
        'shopOptions' => $shopOptions,
        'showShopFilter' => $showShopFilter ?? false,
        'defaultOpen' => $hasActiveFilters,
    ])

    @if(! empty($shopeeLastReconcileByShop))
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-700" data-testid="omnichannel-missed-push-last-poll">
        <span class="font-semibold text-gray-900">Terakhir reconcile poll (Shopee)</span>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
            @foreach($shopeeLastReconcileByShop as $shopId => $polledAt)
            <li><span class="font-mono">shop {{ $shopId }}</span>: {{ $polledAt ? \Illuminate\Support\Carbon::parse($polledAt)->format('Y-m-d H:i') : '—' }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
    @endif
    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('success') }}</div>
    @endif

  <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-700" data-testid="omnichannel-missed-push-config-hint">
        Jalankan <code class="text-xs">php artisan marketplace:reconcile-missed-orders</code> (cron harian atau manual) dengan
        <code>MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED=true</code>.
        Aktifkan tugas terjadwal di Cron Manager setelah env siap.
    </div>

    @if(! ($missedPushTableExists ?? false))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900" data-testid="omnichannel-missed-push-missing-migration">
        <p class="font-medium">Tabel belum ada</p>
        <p class="mt-1">Jalankan migrasi:</p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-white/80 px-3 py-2 font-mono text-xs text-gray-800">php artisan migrate --path=database/migrations/2026_11_27_100000_install_marketplace_missed_push_gaps_table.php</pre>
    </div>
    @elseif($rows === null || $rows->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300" data-testid="omnichannel-missed-push-empty">
        Tidak ada gap terbuka untuk filter ini.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500">
            {{ number_format($rows->total()) }} gap terbuka — halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
            · <code>marketplace_missed_push_gaps</code>
        </div>
        <div class="min-w-0 overflow-x-auto">
            <table class="w-full min-w-[40rem] text-left" data-testid="omnichannel-missed-push-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-14">ID</th>
                        <th class="{{ $th }} w-28">detected</th>
                        <th class="{{ $th }} w-24">channel</th>
                        <th class="{{ $th }} w-24">shop</th>
                        <th class="{{ $th }}">order_ref</th>
                        <th class="{{ $th }} w-32">order_status</th>
                        <th class="{{ $th }} w-28">platform upd</th>
                        <th class="{{ $th }} w-28 text-right">aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    <tr class="hover:bg-gray-50/80" data-testid="omnichannel-missed-push-row-{{ $row->id }}">
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->id }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->detected_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }}">{{ ucfirst($row->channel) }}</td>
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->shop_ref ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref }}</td>
                        <td class="{{ $td }} text-[11px]">{{ $row->order_status ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->platform_update_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="{{ $td }} text-right">
                            @if($row->channel === 'shopee')
                            <form method="post" action="{{ route('omnichannel.orders.missed-push.ingest', ['gap' => $row->id]) }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded border border-blue-600 px-2 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-50" data-testid="omnichannel-missed-push-ingest-{{ $row->id }}">Ingest</button>
                            </form>
                            @else
                            <span class="text-[11px] text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
        <div class="border-t border-gray-100 px-3 py-3">
            {{ $rows->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection