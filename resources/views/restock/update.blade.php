@extends('layouts.app')

@section('title', 'Update ' . ($restock->item->name ?? 'Restock'))

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'Update Quantities', 'href' => '#'],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-zinc-900">Update {{ $restock->item->name ?? '' }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500">
            <span class="font-medium text-zinc-700">{{ $restock->item->code ?? '' }}</span>
            <span class="h-4 w-px bg-zinc-200"></span>
            <span>Current Status:</span>
            <div class="flex gap-2 font-mono">
                <span title="Restocked">R: {{ $restock->restocked_quantity }}</span>
                <span title="Production">P: {{ $restock->in_production_quantity }}</span>
                <span title="Shipped">S: {{ $restock->shipped_quantity }}</span>
                <span title="Missing">M: {{ $restock->missing_quantity }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('restock.updateQty', $restock->id) }}" class="space-y-6">
                    @csrf
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Action Type</label>
                            <select name="type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                                <option value="restocked" @selected(old('type','restocked')==='restocked')>Add Restocked Qty</option>
                                <option value="production" @selected(old('type')==='production')>Move to Production</option>
                                <option value="shipped" @selected(old('type')==='shipped')>Move to Shipped</option>
                                <option value="missing" @selected(old('type')==='missing')>Add Missing Qty</option>
                            </select>
                            @error('type')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Quantity</label>
                            <input type="number" name="qty" min="1" value="{{ old('qty', 0) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('qty')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Invoice / Note (Optional)</label>
                            <input name="invoice" value="{{ old('invoice') }}" placeholder="e.g. INV-123" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('invoice')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Date</label>
                            <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('date')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex items-center justify-end border-t border-zinc-200 pt-6">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-1">
            <div class="rounded-xl border border-red-200 bg-red-50/50 p-6">
                <div class="mb-4 flex items-center gap-2 text-red-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.73 4a2 2 0 00-3.46 0L3.34 16a2 2 0 001.73 3z"/></svg>
                    <h2 class="text-lg font-semibold">Danger Zone</h2>
                </div>
                <p class="mb-6 text-sm text-red-700">Use these actions with caution. Resetting will set the quantity of a specific state back to zero.</p>
                <div class="space-y-3">
                    @foreach(['restocked' => 'Reset Restocked Qty', 'production' => 'Reset Production Qty', 'shipped' => 'Reset Shipped Qty'] as $type => $label)
                    <form method="POST" action="{{ route('restock.resetSingleQty', $restock->id) }}" onsubmit="return confirm('Are you sure you want to reset {{ $type }} quantity to 0?')">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <button type="submit" class="inline-flex w-full items-center justify-start gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            {{ $label }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
