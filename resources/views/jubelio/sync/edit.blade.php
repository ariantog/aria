@extends('layouts.app')

@section('title', 'Edit Jubelio Sync Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Sync Mapping', 'href' => route('jubelio.sync.index')],
    ['title' => 'Edit Mapping', 'href' => route('jubelio.sync.edit', $sync->id)],
];
$warehouseRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'sender', 'addrbook_type' => $addrbookTypes['warehouse']]);
$customerRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'receiver', 'addrbook_type' => $addrbookTypes['customer']]);
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('jubelio.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Edit Sync Mapping</h1>
    </div>

    <div class="max-w-2xl space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="flex items-center gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4">
                <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18l-2 9H5L3 3zm0 0l-.75-3M5 12l-1 8h16l-1-8"/></svg>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Jubelio Store</p>
                    <p class="text-sm font-medium">{{ $sync->jubelio_store_name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4">
                <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase">Jubelio Location</p>
                    <p class="text-sm font-medium">{{ $sync->jubelio_location_name }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-sm font-semibold text-gray-900">Internal Mapping</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('jubelio.sync.update', $sync->id) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id', $sync->warehouse_id) }}">
                    <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $sync->customer_id) }}">

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Target Warehouse (Internal)</label>
                        @include('jubelio.partials.lookup-combobox', [
                            'endpoint' => $warehouseRoute,
                            'placeholder' => 'Search warehouse...',
                            'hiddenField' => 'warehouse_id',
                            'initialId' => old('warehouse_id', $sync->warehouse_id),
                            'initialName' => $sync->warehouse->name ?? null,
                        ])
                        @error('warehouse_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Target Customer (Optional)</label>
                        @include('jubelio.partials.lookup-combobox', [
                            'endpoint' => $customerRoute,
                            'placeholder' => 'Search customer...',
                            'hiddenField' => 'customer_id',
                            'initialId' => old('customer_id', $sync->customer_id),
                            'initialName' => $sync->customer->name ?? null,
                        ])
                        @error('customer_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <a href="{{ route('jubelio.sync.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
