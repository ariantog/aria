@extends('layouts.app')

@section('title', 'Received Cart')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'Received Cart', 'href' => route('restock.received')],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-zinc-900">Received Cart</h1>
        <p class="text-zinc-500">Verify and receive items into the warehouse (Gudang).</p>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 p-6"><h2 class="text-lg font-semibold">Items to Receive</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="px-6 py-4 font-bold">Item</th>
                                <th class="px-6 py-4 font-bold">Quantity</th>
                                <th class="px-6 py-4 text-right font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse($items as $item)
                            <tr class="transition-colors hover:bg-zinc-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-900">{{ $item['name'] }}</div>
                                    <div class="text-xs text-zinc-500">{{ $item['code'] }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono">{{ $item['quantity'] }}</td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" action="{{ route('restock.removeCartItem', $item['code']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-6 py-12 text-center italic text-zinc-400">Your cart is empty. Add items from the Restock list.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Complete Receiving</h2>
                <form method="POST" action="{{ route('restock.receiveStore') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium">Arrival Date</label>
                        <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('date')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Invoice / Ref (Optional)</label>
                        <input name="invoice" value="{{ old('invoice') }}" placeholder="e.g. SJ-123" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('invoice')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    @error('gudang')<div class="rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $message }}</div>@enderror
                    <button type="submit" @if(count($items) === 0) disabled @endif class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Confirm &amp; Add to Stock
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
