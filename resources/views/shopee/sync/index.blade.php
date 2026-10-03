@extends('layouts.app')

@section('title', 'Shopee Warehouse Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold">Shopee Warehouse Mapping</h1>
            <p class="mt-1 text-sm text-gray-500">Map Aria gudang to Shopee pickup warehouse / stock location for live sellable qty.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('shopee.sync.index') }}" class="flex items-center gap-2">
                <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Search Shopee warehouse..." class="h-9 w-64 rounded-md border border-gray-300 px-3 text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700">Search</button>
            </form>
            <a href="{{ route('shopee.sync.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700">
                Create Mapping
            </a>
        </div>
    </div>

    @if(! ($connection['ready'] ?? false))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Shopee shop belum ter-authorize. Buka
            <a href="{{ route('shopee-ads.index') }}" class="font-medium text-orange-700 underline">Shopee Ads</a>
            → Authorize Shopee (OAuth yang sama dipakai untuk cek stok produk).
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-6 py-4">Shopee warehouse</th>
                    <th class="px-6 py-4">Location ID</th>
                    <th class="px-6 py-4">Aria warehouse</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($dataList as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium">{{ $row->shopee_warehouse_name }}</td>
                    <td class="px-6 py-4 font-mono text-xs">{{ $row->shopee_location_id ?: '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex rounded border border-orange-500/20 px-2 py-0.5 font-medium text-orange-700">{{ $row->warehouse->name ?? 'Unknown' }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('shopee.sync.edit', $row->id) }}" class="text-orange-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('shopee.sync.delete', $row->id) }}" class="ml-3 inline" onsubmit="return confirm('Delete this mapping?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No mappings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $dataList, 'label' => 'mappings'])
    </div>
</div>
@endsection
