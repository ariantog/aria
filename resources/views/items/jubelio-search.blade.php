@extends('layouts.app')

@section('title', 'Link Jubelio Item')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => $item->name, 'href' => route('items.show', $item->id)],
    ['title' => 'Jubelio Search', 'href' => '#'],
];
@endphp

<div class="p-4 sm:p-8">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('items.jubelio', $item->id) }}" class="flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Link Item to Jubelio</h1>
                <p class="text-gray-500">Current item: <span class="font-mono text-blue-600">{{ $item->code }}</span> - {{ $item->name }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                <h3 class="font-semibold text-gray-900">Search Jubelio Catalog</h3>
                <form method="GET" action="{{ route('items.jubelio-search', $item->id) }}" class="flex gap-2 pt-4">
                    <input type="text" name="q" value="{{ $query }}" placeholder="Search by code or name..."
                           class="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Search
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th class="px-6 py-3 font-medium">Jubelio Code</th>
                            <th class="px-6 py-3 font-medium">Item Name</th>
                            <th class="px-6 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($jubelioItems as $jub)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-3 font-mono text-blue-600">{{ $jub['item_code'] ?? '-' }}</td>
                            <td class="px-6 py-3 text-gray-900">{{ $jub['item_name'] ?? '-' }}</td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('items.jubelio-link', $item->id) }}">
                                    @csrf
                                    <input type="hidden" name="jubelio_item_id" value="{{ $jub['item_id'] ?? '' }}">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-600 hover:bg-green-600 hover:text-white">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 0 4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656M10.172 13.828a4 4 0 01-5.656 0 4 4 0 010-5.656l3-3a4 4 0 015.656 5.656"/></svg>
                                        Link
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-6 py-12 text-center italic text-gray-500">No results found in Jubelio.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
