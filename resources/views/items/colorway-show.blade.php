@extends('layouts.app')

@section('title', 'Colorway: ' . ($color['code'] ?? $group->name))

@section('content')
@php
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $isAsset ? route('assetlancar.index') : route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $sample->pcode, 'href' => route('items.group-parent-detail', $parentGroupId)],
    ['title' => $color['code'] ?? 'Colorway', 'href' => '#'],
];
$matrixPricingFields = [
    'price' => ['label' => 'Price', 'decimals' => 0, 'key' => 'effective_price'],
    'reseller_price' => ['label' => 'Reseller', 'decimals' => 0, 'key' => 'effective_reseller_price'],
];
if ($isAsset) {
    $matrixPricingFields['cost'] = ['label' => 'Cost', 'decimals' => 0, 'key' => 'effective_cost'];
    $matrixPricingFields['cost_cnh'] = ['label' => 'Cost CNY', 'decimals' => 2, 'key' => 'effective_cost_cnh'];
}
@endphp

<div class="p-4 sm:p-6" x-data="{ showZero: false }">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('items.group-parent-detail', $parentGroupId) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to parent group
        </a>
        @if($canEditColorway ?? false)
        <a href="{{ route('items.colorway-edit', $group) }}"
           data-testid="colorway-show-edit-link"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Edit colorway
        </a>
        @endif
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white lg:col-span-1">
            <div class="flex min-h-[240px] items-center justify-center p-4">
                @if($group->image_url)
                    <img src="{{ $group->image_url }}" alt="{{ $effectiveProductTitle }}" class="max-h-[320px] w-auto object-contain">
                @else
                    <svg class="h-20 w-20 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4z"/></svg>
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-2" data-testid="colorway-show-summary">
            <div class="border-b border-gray-100 px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">{{ $effectiveProductTitle !== '' ? $effectiveProductTitle : ($group->name ?: $sample->pcode) }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    <span class="font-semibold uppercase text-purple-800">{{ $color['code'] ?? '—' }}</span>
                    @if(!empty($color['name']))
                        <span class="text-gray-600">· {{ $color['name'] }}</span>
                    @endif
                </p>
            </div>
            <div class="grid grid-cols-1 gap-x-8 gap-y-4 p-6 md:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">PCode</p>
                    <p class="font-mono text-sm text-gray-900">{{ $sample->pcode }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Parent product name</p>
                    <p class="text-sm text-gray-900">{{ $parentProductName !== '' ? $parentProductName : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Master / variant</p>
                    <p class="font-mono text-sm text-gray-700">{{ $group->master ?: '—' }} / {{ $group->variant ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stored colorway title</p>
                    <p class="text-sm text-gray-900">
                        {{ $productTitle !== '' ? $productTitle : '— (inherits parent)' }}
                        @if($usesPlaceholder)
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">pcode placeholder</span>
                        @endif
                    </p>
                </div>
                @if($typeTag)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Type</p>
                    <p class="text-sm text-gray-900">{{ $typeTag->code }} — {{ $typeTag->name }}</p>
                </div>
                @endif
                @if($jahitTag && ! $isAsset)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Jahit</p>
                    <p class="text-sm text-gray-900">{{ $jahitTag->code }}</p>
                </div>
                @endif
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Description</p>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ $group->description ?: '—' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Notes (NB)</p>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ $group->description2 ?: '—' }}</p>
                </div>
                @if($group->url)
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">URL</p>
                    <a href="{{ $group->url }}" class="text-sm text-blue-600 underline hover:text-blue-800" target="_blank" rel="noopener">{{ $group->url }}</a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-gray-900">Sizes &amp; pricing</h2>
        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
            <input type="checkbox" x-model="showZero" class="rounded border-gray-300"> Show 0 quantity
        </label>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-show-sku-table">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Size</th>
                        <th class="px-4 py-3 text-left font-semibold">SKU</th>
                        @foreach($matrixPricingFields as $fieldMeta)
                        <th class="px-4 py-3 text-right font-semibold">{{ $fieldMeta['label'] }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-right font-semibold">Restock</th>
                        <th class="px-4 py-3 text-right font-semibold">Aria qty</th>
                        <th class="px-4 py-3 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sizeRows as $row)
                    <tr class="hover:bg-gray-50/50" x-show="showZero || {{ (float) ($row['warehouse_qty'] ?? 0) > 0 ? 'true' : 'false' }}" x-cloak>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $row['size_code'] }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ $row['show_url'] }}" class="font-mono text-blue-600 hover:underline">{{ $row['code'] }}</a>
                            <div class="text-xs text-gray-500">{{ $row['name'] }}</div>
                        </td>
                        @foreach($matrixPricingFields as $fieldKey => $fieldMeta)
                        @php $amount = (float) ($row[$fieldMeta['key']] ?? 0); @endphp
                        <td class="px-4 py-3 text-right font-mono text-gray-800">
                            {{ $amount > 0 ? format_amount($amount, $fieldMeta['decimals']) : '—' }}
                        </td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-mono text-gray-700">
                            {{ (int) ($row['restock_urgent_threshold'] ?? 0) > 0 ? $row['restock_urgent_threshold'] : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-gray-800">{{ format_amount($row['warehouse_qty'] ?? 0, 0) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ $row['edit_url'] }}" class="text-xs text-gray-500 hover:text-gray-800">SKU edit</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
