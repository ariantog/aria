@extends('layouts.app')

@section('title', 'Marketplace — Warehouse mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.settings.index', ['tab' => 'warehouse'])],
    ['title' => 'Gudang marketplace', 'href' => route('marketplace.warehouse-sync.index')],
];
$channel = $channel ?? 'tiktok';
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="marketplace-warehouse-sync-index">
    @if($flash['success'] ?? null)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">{{ $flash['error'] }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Gudang marketplace</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Shopee + TikTok Shop / Tokopedia Partner — satu baris per gudang platform.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('marketplace.warehouse-sync.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="channel" value="{{ $channel }}">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari shop / nama…" class="h-9 w-56 rounded-md border border-gray-300 px-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Cari</button>
            </form>
            <a href="{{ route('marketplace.warehouse-sync.create', ['channel' => $channel]) }}" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Tambah / sinkron</a>
        </div>
    </div>

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700" data-testid="marketplace-warehouse-sync-tabs">
        <a href="{{ route('marketplace.warehouse-sync.index', array_merge($filters, ['channel' => 'shopee'])) }}"
           class="px-4 py-2 text-sm font-medium {{ $channel === 'shopee' ? 'border-b-2 border-orange-600 text-orange-700 dark:text-orange-400' : 'text-gray-600 dark:text-gray-400' }}">Shopee</a>
        <a href="{{ route('marketplace.warehouse-sync.index', array_merge($filters, ['channel' => 'tiktok'])) }}"
           class="px-4 py-2 text-sm font-medium {{ $channel === 'tiktok' ? 'border-b-2 border-gray-900 text-gray-900 dark:border-gray-100 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400' }}">TikTok / Tokopedia</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">shop_ref</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3 text-right">Baris</th>
                    <th class="px-4 py-3 text-right">Belum dipetakan</th>
                    <th class="px-4 py-3">Terakhir sinkron</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($shopSummaries as $shop)
                @php $matrixShopRef = filled($shop->shop_ref) ? $shop->shop_ref : '_oauth'; @endphp
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $shop->shop_ref ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $shop->shop_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs">{{ $shop->warehouse_count }}</td>
                    <td class="px-4 py-3 text-right">
                        @if((int) $shop->unmapped_count > 0)
                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-100">{{ $shop->unmapped_count }}</span>
                        @else
                        <span class="text-gray-500">0</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ $shop->last_synced_at ? \Illuminate\Support\Carbon::parse($shop->last_synced_at)->format('Y-m-d H:i') : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('marketplace.warehouse-sync.matrix', ['channel' => $channel, 'shopRef' => $matrixShopRef]) }}" class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-400">Matrix</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data. Sinkron dari API atau tunggu backfill dari mapping lama.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $shopSummaries->links() }}</div>
</div>
@endsection