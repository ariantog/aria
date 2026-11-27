@extends('layouts.app')

@section('title', 'Marketplace item links')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.index')],
    ['title' => 'Item links', 'href' => route('omnichannel.item-links.index')],
];
$buildQuery = fn (array $overrides = []) => array_filter(
    array_merge([
        'warehouse_id' => $filters['warehouse_id'] ?? null,
        'channel' => $filters['channel'] ?? 'both',
        'stock_only' => ($filters['stock_only'] ?? true) ? '1' : '0',
        'q' => $filters['q'] ?? '',
    ], $overrides),
    fn ($v) => $v !== null && $v !== ''
);
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900" data-testid="omnichannel-item-links-title">Marketplace item links</h1>
            <p class="mt-1 text-sm text-gray-500">
                SKU di gudang terhubung Shopee / TikTok yang belum punya ID marketplace — default: hanya baris dengan stok &gt; 0 di gudang dipilih.
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            @if($can['shopee'] ?? false)
            <a href="{{ route('shopee.auto-link.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 font-medium text-gray-700 hover:bg-gray-50">Shopee auto-link</a>
            <a href="{{ route('shopee.bulk-link.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 font-medium text-gray-700 hover:bg-gray-50">Shopee bulk-link</a>
            @endif
            @if($can['tiktok'] ?? false)
            <a href="{{ route('tiktok-shop.bulk-link.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 font-medium text-gray-700 hover:bg-gray-50">TikTok bulk-link</a>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('omnichannel.item-links.index') }}" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 lg:grid-cols-6">
        <div class="space-y-1 lg:col-span-2">
            <label class="text-xs font-semibold uppercase text-gray-500" for="warehouse_id">Gudang</label>
            <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="omnichannel-item-links-warehouse">
                @forelse($warehouseOptions as $wh)
                <option value="{{ $wh['id'] }}" @selected((int) ($filters['warehouse_id'] ?? 0) === (int) $wh['id'])>
                    {{ $wh['name'] }}
                    @if(!empty($wh['channels']))
                    ({{ implode(', ', $wh['channels']) }})
                    @endif
                </option>
                @empty
                <option value="">— tidak ada gudang terhubung —</option>
                @endforelse
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500" for="channel">Channel</label>
            <select name="channel" id="channel" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="omnichannel-item-links-channel">
                <option value="both" @selected(($filters['channel'] ?? 'both') === 'both')>Shopee + TikTok</option>
                <option value="shopee" @selected(($filters['channel'] ?? '') === 'shopee')>Shopee saja</option>
                <option value="tiktok" @selected(($filters['channel'] ?? '') === 'tiktok')>TikTok saja</option>
            </select>
        </div>
        <div class="space-y-1 lg:col-span-2">
            <label class="text-xs font-semibold uppercase text-gray-500" for="q">Cari SKU</label>
            <input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode / nama…" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="space-y-1">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="stock_only" value="0">
                <input type="checkbox" name="stock_only" value="1" class="rounded border-gray-300" @checked($filters['stock_only'] ?? true) data-testid="omnichannel-item-links-stock-only">
                Stok &gt; 0 saja
            </label>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
            <a href="{{ route('omnichannel.item-links.export', $buildQuery()) }}" class="flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" data-testid="omnichannel-item-links-export">CSV</a>
        </div>
    </form>

    @if($warehouseOptions === [])
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
        Belum ada gudang dengan mapping Shopee / TikTok. Atur di
        <a href="{{ route('shopee.sync.index') }}" class="font-medium underline">Shopee warehouse map</a>,
        <a href="{{ route('tiktok-shop.sync.index') }}" class="font-medium underline">TikTok sync</a>, atau
        <a href="{{ route('marketplace.warehouse-sync.index') }}" class="font-medium underline">marketplace warehouse matrix</a>.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" data-testid="omnichannel-item-links-table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">SKU</th>
                        <th class="px-4 py-3 font-bold">Nama</th>
                        <th class="px-4 py-3 font-bold text-right">Qty gudang</th>
                        <th class="px-4 py-3 font-bold">Belum terisi</th>
                        <th class="px-4 py-3 font-bold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $row)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-mono text-gray-900">{{ $row['code'] }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $row['name'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-900">{{ number_format($row['warehouse_qty'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($row['missing_fields'] as $field)
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-[11px] text-amber-900">{{ $field }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex flex-wrap justify-end gap-2">
                                <a href="{{ $row['show_url'] }}" class="text-xs font-medium text-blue-600 hover:underline">Item</a>
                                @if(($can['shopee'] ?? false) && $row['shopee_unlinked'])
                                <a href="{{ $row['shopee_link_url'] }}" class="text-xs font-medium text-orange-700 hover:underline">Shopee link</a>
                                @endif
                                @if(($can['tiktok'] ?? false) && $row['tiktok_unlinked'])
                                <a href="{{ $row['tiktok_link_url'] }}" class="text-xs font-medium text-violet-700 hover:underline">TikTok link</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">Tidak ada SKU yang cocok dengan filter.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
        <div class="border-t border-gray-200 px-4 py-3">
            {{ $items->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection