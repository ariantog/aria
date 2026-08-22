@extends('layouts.app')

@section('title', 'Item Groups')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
];
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-gray-900">Group List</h1>
            <p class="text-gray-500">Parent groups by TYPE + pcode (manufactured) or asset pcode</p>
        </div>
    </div>

    <form method="GET" action="{{ route('items.group') }}" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4">
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500">Parent Code</label>
            <input name="kode" value="{{ $filters['kode'] ?? '' }}" placeholder="e.g. AJD CX93024 or GLOVE-01" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
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
            <a href="{{ route('items.group') }}" class="flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Parent (TYPE + PCode)</th>
                        <th class="px-6 py-3 font-bold">Product</th>
                        <th class="px-6 py-3 font-bold">Variants</th>
                        <th class="px-6 py-3 font-bold">SKUs</th>
                        <th class="px-6 py-3 font-bold">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($parents as $parent)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-3 font-medium">
                            <a href="{{ route('items.group-parent-detail', $parent['parent_slug']) }}" class="font-mono text-blue-600 hover:underline">{{ $parent['label'] }}</a>
                            @if($parent['is_asset'])
                            <span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Asset</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-gray-700">{{ $parent['product_name'] ?: '—' }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $parent['variant_count'] ?? '—' }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $parent['sku_count'] }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ \Illuminate\Support\Str::limit($parent['description'] ?? '', 60) ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No groups found matching your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $parents, 'label' => 'groups'])
    </div>
</div>
@endsection
