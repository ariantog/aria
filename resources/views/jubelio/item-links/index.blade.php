@extends('layouts.app')

@section('title', 'Jubelio Item Links')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Item Links', 'href' => route('jubelio.item-links.index')],
];
$groupTabUrl = route('jubelio.item-links.index', array_filter([
    'kode' => $view === 'group' ? ($filters['kode'] ?? '') : null,
    'product_name' => $view === 'group' ? ($filters['product_name'] ?? '') : null,
    'desc' => $view === 'group' ? ($filters['desc'] ?? '') : null,
]));
$itemsTabUrl = route('jubelio.item-links.index', array_filter([
    'view' => 'items',
    'q' => $view === 'items' ? ($filters['q'] ?? '') : null,
    'link' => $view === 'items' ? ($filters['link'] ?? 'all') : null,
], fn ($v) => $v !== null && $v !== ''));
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Jubelio Item Links</h1>
        <p class="mt-1 text-sm text-gray-500">Cek apakah SKU Aria sudah punya <span class="font-mono">jubelio_item_id</span> (terhubung ke Jubelio). Tidak memanggil API Jubelio — hanya status di database.</p>
    </div>

    <div class="mb-6 flex gap-2 border-b border-gray-200">
        <a href="{{ $groupTabUrl }}"
           class="border-b-2 px-4 py-2 text-sm font-medium {{ $view === 'group' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Per grup
        </a>
        <a href="{{ $itemsTabUrl }}"
           class="border-b-2 px-4 py-2 text-sm font-medium {{ $view === 'items' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Per SKU
        </a>
    </div>

    @if($view === 'group')
        <form method="GET" action="{{ route('jubelio.item-links.index') }}" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4">
            <div class="space-y-1">
                <label class="text-xs font-semibold uppercase text-gray-500">Parent Code</label>
                <input name="kode" value="{{ $filters['kode'] ?? '' }}" placeholder="e.g. CX90233 or GLOVE-01" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold uppercase text-gray-500">Product Name</label>
                <input name="product_name" value="{{ $filters['product_name'] ?? '' }}" placeholder="Filter product name…" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold uppercase text-gray-500">Description</label>
                <input name="desc" value="{{ $filters['desc'] ?? '' }}" placeholder="Filter description…" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
                <a href="{{ route('jubelio.item-links.index') }}" class="flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-bold">Parent</th>
                            <th class="px-6 py-3 font-bold">Product</th>
                            <th class="px-6 py-3 font-bold">SKUs</th>
                            <th class="px-6 py-3 font-bold">Linked</th>
                            <th class="px-6 py-3 font-bold">Unlinked</th>
                            <th class="px-6 py-3 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($parents as $parent)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-3 font-mono text-gray-900">{{ $parent['label'] }}</td>
                            <td class="px-6 py-3 text-gray-700">{{ $parent['product_name'] ?: '—' }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $parent['total_sku_count'] }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">{{ $parent['linked_sku_count'] }}</span>
                            </td>
                            <td class="px-6 py-3">
                                @if($parent['unlinked_sku_count'] > 0)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $parent['unlinked_sku_count'] }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('jubelio.item-links.group', $parent['parent_group_id']) }}"
                                   class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    Cek SKU
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Tidak ada grup yang cocok dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $parents, 'label' => 'groups'])
        </div>
    @else
        <form method="GET" action="{{ route('jubelio.item-links.index') }}" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4">
            <input type="hidden" name="view" value="items">
            <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-semibold uppercase text-gray-500">Search</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="SKU code, name, atau legacy code…" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold uppercase text-gray-500">Status link</label>
                <select name="link" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="all" @selected(($filters['link'] ?? 'all') === 'all')>Semua</option>
                    <option value="linked" @selected(($filters['link'] ?? '') === 'linked')>Sudah linked</option>
                    <option value="unlinked" @selected(($filters['link'] ?? '') === 'unlinked')>Belum linked</option>
                    <option value="auto_linked" @selected(($filters['link'] ?? '') === 'auto_linked')>Auto-linked</option>
                    <option value="auto_failed" @selected(($filters['link'] ?? '') === 'auto_failed')>Auto-link gagal (5x)</option>
                    <option value="ambiguous" @selected(($filters['link'] ?? '') === 'ambiguous')>Ambiguous</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
                <a href="{{ route('jubelio.item-links.index', ['view' => 'items']) }}" class="flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-bold">SKU</th>
                            <th class="px-6 py-3 font-bold">Name</th>
                            <th class="px-6 py-3 font-bold">Jubelio ID</th>
                            <th class="px-6 py-3 font-bold">Status</th>
                            <th class="px-6 py-3 font-bold">Auto-link</th>
                            <th class="px-6 py-3 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($items as $item)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-3 font-mono">
                                <a href="{{ $item['show_url'] }}" class="text-blue-600 hover:underline">{{ $item['code'] }}</a>
                            </td>
                            <td class="px-6 py-3 text-gray-700">{{ $item['name'] }}</td>
                            <td class="px-6 py-3 font-mono text-gray-600">{{ $item['jubelio_item_id'] ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @if($item['linked'])
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Linked</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">Not linked</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-xs text-gray-600">
                                @if($item['auto_link_outcome'] ?? null)
                                    <span class="font-mono">{{ $item['auto_link_outcome'] }}</span>
                                    @if($item['auto_link_checked_at'] ?? null)
                                        <div class="text-gray-400">{{ $item['auto_link_checked_at'] }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ $item['jubelio_url'] }}" class="text-xs font-medium text-blue-600 hover:underline">Kelola link</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Tidak ada item yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $items, 'label' => 'items'])
        </div>
    @endif
</div>
@endsection
