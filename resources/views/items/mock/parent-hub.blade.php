@extends('layouts.app')

@section('title', 'Mock hub: ' . $detail['label'])

@section('content')
@php
$breadcrumbs = [
    ['title' => $detail['is_asset'] ? 'Assets' : 'Items', 'href' => $detail['is_asset'] ? '/assetlancar' : '/items'],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $detail['label'], 'href' => route('items.group-parent-detail', $detail['anchor_group_id'])],
    ['title' => 'Mock hub', 'href' => '#'],
];
$liveParentUrl = route('items.group-parent-detail', $detail['anchor_group_id']);
$titleBadgeClass = fn (string $source) => match ($source) {
    'parent' => 'bg-blue-100 text-blue-800',
    'colorway' => 'bg-purple-100 text-purple-800',
    'pcode' => 'bg-amber-100 text-amber-900',
    'sku' => 'bg-gray-200 text-gray-800',
    default => 'bg-gray-100 text-gray-700',
};
@endphp

<div class="p-4 sm:p-6" x-data="{ tab: 'colorways' }">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveParentUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'parent',
        'groupId' => $detail['anchor_group_id'],
        'activeVariant' => 'a',
    ])

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ $liveParentUrl }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Current parent page
        </a>
        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">Variant A · tabbed hub</span>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-6 py-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Style</p>
            <div class="mt-1 flex flex-wrap items-baseline gap-3">
                <h1 class="text-2xl font-bold text-gray-900">
                    @if($detail['uses_placeholder'])
                        <span class="text-gray-400">No product title</span>
                    @else
                        {{ $detail['product_name'] }}
                    @endif
                </h1>
                @if($detail['uses_placeholder'])
                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Using style code in lists until you set a title</span>
                @endif
            </div>
            <p class="mt-1 font-mono text-sm text-gray-500">Style key: {{ $detail['label'] }}</p>
        </div>
        <div class="flex border-b border-gray-200 px-4">
            <button type="button" @click="tab = 'colorways'"
                    class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    :class="tab === 'colorways' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    data-testid="mock-tab-colorways">
                Colorways
            </button>
            <button type="button" @click="tab = 'defaults'"
                    class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    :class="tab === 'defaults' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    data-testid="mock-tab-defaults">
                Style defaults
            </button>
            <button type="button" @click="tab = 'overview'"
                    class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    :class="tab === 'overview' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    data-testid="mock-tab-overview">
                Overview
            </button>
        </div>

        <div x-show="tab === 'colorways'" class="p-4 sm:p-6" data-testid="mock-panel-colorways">
            <p class="mb-4 text-sm text-gray-600">
                One row per color. <strong>Effective title</strong> is what SKU display names use (bare title + warna + size).
                Edit catalog on the colorway; rename all colors from Style defaults.
            </p>
            @include('items.mock.partials.colorway-catalog-table', [
                'colorwayCatalogRows' => $colorwayCatalogRows,
                'canEditGroup' => $canEditGroup,
                'showLiveLinks' => true,
            ])
        </div>

        <div x-show="tab === 'defaults'" x-cloak class="p-4 sm:p-6" data-testid="mock-panel-defaults">
            <p class="mb-4 text-sm text-gray-600">
                Proposed copy: <strong>Style title (default for all colorways)</strong> — maps to parent <span class="font-mono">product_name</span>.
                Saves would offer “update default only” vs “replace all colorway titles” (mock controls disabled).
            </p>
            <fieldset disabled class="space-y-6 opacity-90">
                <div class="max-w-xl">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Style title (all colorways)</label>
                    <input type="text"
                           value="{{ $detail['uses_placeholder'] ? '' : $detail['product_name'] }}"
                           placeholder="{{ $detail['label'] }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                           data-testid="mock-parent-title-input">
                    <p class="mt-1 text-xs text-gray-500">Empty = lists fall back to style code per colorway until a title is set.</p>
                </div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300">
                    <span>Replace all colorway titles so every color inherits this style title</span>
                </label>
                @unless($detail['is_asset'])
                <div class="grid max-w-xl grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Brand (all colorways)</label>
                        <select class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option>— (mock)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Type tag (all colorways)</label>
                        <select class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option>— (mock)</option>
                        </select>
                    </div>
                </div>
                @endunless
                <button type="button" class="rounded-lg bg-gray-400 px-4 py-2 text-sm font-medium text-white cursor-not-allowed">
                    Save style defaults (mock)
                </button>
            </fieldset>
        </div>

        <div x-show="tab === 'overview'" x-cloak class="p-4 sm:p-6" data-testid="mock-panel-overview">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold text-gray-500">Aria stock (physical)</p>
                    @include('items.partials.warehouse-qty-breakdown', [
                        'total' => $detail['total_warehouse_qty'],
                        'warehouses' => $detail['warehouse_breakdown'] ?? [],
                        'align' => 'left',
                    ])
                </div>
                @if($detail['description'])
                <div>
                    <p class="text-sm font-semibold text-gray-500">Description (aggregated)</p>
                    <p class="text-sm text-gray-700">{{ $detail['description'] }}</p>
                </div>
                @endif
            </div>
            <p class="mt-6 text-xs text-gray-500">
                Full warehouse tables and Jubelio columns stay on the live parent page; this tab is a lightweight summary in the mock hub.
            </p>
        </div>
    </div>
</div>
@endsection
