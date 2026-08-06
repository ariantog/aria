@extends('layouts.app')

@section('title', 'Item Groups')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
];
@endphp

<div class="p-4 sm:p-6" x-data="{ showImage: true }">
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-gray-900">Group List</h1>
            <p class="text-gray-500">View and manage item groups and their collective stock</p>
        </div>
        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
            <input type="checkbox" x-model="showImage" class="rounded border-gray-300"> Show Images
        </label>
    </div>

    <form method="GET" action="{{ route('items.group') }}" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4">
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500">Kode</label>
            <input name="kode" value="{{ $filters['kode'] ?? '' }}" placeholder="Filter Kode..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500">Product Name</label>
            <input name="product_name" value="{{ $filters['product_name'] ?? '' }}" placeholder="Filter product name…" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500">Description</label>
            <input name="desc" value="{{ $filters['desc'] ?? '' }}" placeholder="Filter Description..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
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
                        <th class="px-6 py-3 font-bold" :class="{'hidden': !showImage}">Image</th>
                        <th class="px-6 py-3 font-bold">Kode</th>
                        <th class="px-6 py-3 font-bold">Product</th>
                        <th class="px-6 py-3 font-bold">Variant</th>
                        <th class="px-6 py-3 font-bold">Description</th>
                        <th class="px-6 py-3 font-bold">In Warehouse</th>
                        <th class="px-6 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($groups as $g)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-3" :class="{'hidden': !showImage}">
                            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-white">
                                @if($g->image_url)
                                    <img src="{{ $g->image_url }}" alt="{{ $g->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-3 font-medium">
                            <a href="{{ route('items.group-detail', $g->id) }}" class="text-blue-600 hover:underline">{{ $g->name }}</a>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ $g->variant ?: '-' }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $g->description ?: '-' }}</td>
                        <td class="px-6 py-3 font-bold text-green-600">{{ number_format($g->in_warehouse_qty, 0, ',', '.') }}</td>
                        <td class="px-6 py-3 text-right">
                            <form method="POST" action="{{ route('restock.addItem') }}" class="inline">
                                @csrf
                                <input type="hidden" name="code" value="{{ $g->id }}">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    Restock
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No groups found matching your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $groups, 'label' => 'groups'])
    </div>
</div>
@endsection
