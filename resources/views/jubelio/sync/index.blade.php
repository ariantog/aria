@extends('layouts.app')

@section('title', 'Jubelio Sync Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Sync Mapping', 'href' => route('jubelio.sync.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <h1 class="flex items-center gap-2 text-2xl font-bold">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Jubelio Sync Mapping
        </h1>

        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('jubelio.sync.index') }}" class="flex items-center gap-2">
                <div class="relative">
                    <svg class="absolute top-2.5 left-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Search location..." class="h-9 w-64 rounded-md border border-gray-300 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <button type="submit" class="rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Search</button>
                @if(!empty($filters['name']))
                <a href="{{ route('jubelio.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
                @endif
            </form>
            <a href="{{ route('jubelio.sync.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Mapping
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[10px] font-semibold text-gray-600 uppercase">
                    <tr>
                        <th class="px-6 py-4">Store Name</th>
                        <th class="px-6 py-4">Location</th>
                        <th class="px-6 py-4">Warehouse</th>
                        <th class="px-6 py-4">Customer</th>
                        <th class="px-6 py-4">Bin ID</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($dataList as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18l-2 9H5L3 3zm0 0l-.75-3M5 12l-1 8h16l-1-8"/></svg>
                                <span class="font-medium">{{ $item->jubelio_store_name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2 text-xs">
                                <svg class="h-3.5 w-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $item->jubelio_location_name }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded border border-blue-500/20 px-2 py-0.5 font-medium text-blue-600">{{ $item->warehouse->name ?? 'Unknown' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @if($item->customer)
                            <span class="inline-flex rounded border border-green-500/20 px-2 py-0.5 font-medium text-green-600">{{ $item->customer->name }}</span>
                            @else
                            <span class="text-xs text-gray-300 italic">Not Set</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <code class="text-xs">{{ $item->bin_id ?: '-' }}</code>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('jubelio.sync.getBin', $item->id) }}" class="inline-flex h-7 items-center rounded-md border border-gray-300 bg-white px-2 text-[10px] font-medium text-gray-700 hover:bg-gray-50">Set Bin</a>
                                <a href="{{ route('jubelio.sync.edit', $item->id) }}" class="flex h-8 w-8 items-center justify-center rounded-md text-blue-500 hover:bg-gray-100" title="Edit">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('jubelio.sync.delete', $item->id) }}" onsubmit="return confirm('Are you sure you want to delete this mapping?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-gray-100" title="Delete">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400 italic">No sync mappings found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $dataList, 'label' => 'mappings'])
    </div>
</div>
@endsection
