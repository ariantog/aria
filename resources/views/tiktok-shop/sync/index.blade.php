@extends('layouts.app')

@section('title', 'TikTok Shop — Warehouse mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.settings.index', ['tab' => 'warehouse'])],
    ['title' => 'TikTok Shop mapping', 'href' => route('tiktok-shop.sync.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="tiktok-shop-sync-index">
    @if($flash['success'] ?? null)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">{{ $flash['error'] }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">TikTok Shop / Tokopedia (Partner)</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Satu baris per <code class="text-xs">warehouse_id</code> platform → gudang Aria (Tokopedia storefront memakai logistics TikTok yang sama).</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('tiktok-shop.sync.index') }}" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari shop ref / nama…" class="h-9 w-56 rounded-md border border-gray-300 px-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Cari</button>
            </form>
            <a href="{{ route('tiktok-shop.sync.create') }}" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Tambah toko</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">shop_ref (webhook)</th>
                    <th class="px-4 py-3">Nama toko</th>
                    <th class="px-4 py-3 text-right">Gudang platform</th>
                    <th class="px-4 py-3 text-right">Belum dipetakan</th>
                    <th class="px-4 py-3">Terakhir sinkron</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($shopSummaries as $shop)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $shop->shop_ref }}</td>
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
                        <a href="{{ route('tiktok-shop.sync.matrix', ['shopRef' => $shop->shop_ref]) }}" class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-400">Matrix</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada toko. Pilih toko OAuth lalu sinkron gudang platform.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $shopSummaries->links() }}</div>
</div>
@endsection