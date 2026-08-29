@extends('layouts.app')

@section('title', 'Archive — Items')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Archive', 'href' => route('archive.index')],
    ['title' => 'Items', 'href' => route('archive.items.index')],
];
$idr = fn ($v) => 'Rp ' . format_amount($v, 0);
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Archived Items</h1>
            <p class="text-sm text-gray-500">Read-only SKU catalog from the archive database.</p>
        </div>
        <a href="{{ route('archive.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Back to Archive</a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="min-w-[16rem] flex-1">
            <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
            <input type="search" name="search" value="{{ $search }}" placeholder="Name, SKU, pcode, ID"
                   class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm">
        </div>
        <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white text-sm shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-3 font-medium">ID</th>
                        <th class="px-3 py-3 font-medium">SKU</th>
                        <th class="px-3 py-3 font-medium">Name</th>
                        <th class="px-3 py-3 font-medium">Group</th>
                        <th class="px-3 py-3 text-right font-medium">Price</th>
                        <th class="px-3 py-3 text-right font-medium">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono text-xs">
                            <a href="{{ route('archive.items.show', $item->id) }}" class="text-blue-600 hover:underline">#{{ $item->id }}</a>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $item->code }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('archive.items.show', $item->id) }}" class="font-medium text-blue-600 hover:underline">{{ $item->name }}</a>
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $item->group?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $idr($item->price) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $idr($item->cost) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">No archived items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
        <div class="border-t px-3 py-2">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
