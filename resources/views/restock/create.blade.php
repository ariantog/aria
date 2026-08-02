@extends('layouts.app')

@section('title', 'New Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'New Restock', 'href' => route('restock.create')],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-zinc-900">New Restock</h1>
        <p class="text-zinc-500">Enter an Item SKU or Group Code to add items to the restock list.</p>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Add Item</h2>
                <form method="POST" action="{{ route('restock.addItem') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium">ID, SKU, or Group Code</label>
                        <input name="code" value="{{ old('code') }}" placeholder="Enter code..." autofocus class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Quantity</label>
                        <input type="number" name="qty" min="1" value="{{ old('qty', 1) }}" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('qty')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add to List
                    </button>
                </form>
                @if(count($items) > 0)
                <form method="POST" action="{{ route('restock.clearItems') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Clear Entire List
                    </button>
                </form>
                @endif
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 p-6"><h2 class="text-lg font-semibold">Restock List</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="px-6 py-4 font-bold">Group / Item</th>
                                <th class="px-6 py-4 font-bold">Color</th>
                                <th class="px-6 py-4 font-bold">Size / Type</th>
                                <th class="px-6 py-4 font-bold">Quantity</th>
                                <th class="px-6 py-4 text-right font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse($items as $item)
                            @php $uniqueKey = $item['unique_key'] ?? $item['code']; @endphp
                            <tr class="transition-colors hover:bg-zinc-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-900">{{ $item['group_name'] ?? ($item['name'] ?? '-') }}</div>
                                    <div class="text-xs text-zinc-500">{{ $item['code'] ?? '' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if(!empty($item['color_name']))<span class="rounded border border-gray-300 px-2 py-0.5 text-xs">{{ $item['color_name'] }}</span>@else - @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold">{{ $item['size_name'] ?? (($item['size_id'] ?? null) === 'all' ? 'All Sizes' : '-') }}</div>
                                    <div class="text-[10px] uppercase tracking-tighter text-zinc-400">{{ str_replace('-', ' ', $item['size_type'] ?? '') }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono">
                                    <form method="POST" action="{{ route('restock.updateItemQty', $uniqueKey) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="number" name="qty" min="1" value="{{ $item['qty'] }}" class="h-8 w-20 rounded-md border border-gray-300 text-center text-sm">
                                        <button type="submit" title="Update" class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" action="{{ route('restock.removeItem', $uniqueKey) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center italic text-zinc-400">No items added yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(count($items) > 0)
                <div class="border-t border-zinc-200 p-6">
                    <form method="POST" action="{{ route('restock.store') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        @csrf
                        <div class="max-w-xs flex-1">
                            <label class="text-sm font-medium">Restock Date</label>
                            <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('date')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            Save Restock
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
