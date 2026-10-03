@extends('layouts.app')

@section('title', 'Edit Shopee Warehouse Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
    ['title' => 'Edit', 'href' => route('shopee.sync.edit', $sync->id)],
];
$warehouseRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'sender', 'addrbook_type' => $addrbookTypes['warehouse']]);
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('shopee.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Edit Shopee Mapping</h1>
    </div>

    <div class="max-w-2xl space-y-4">
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm">
            <p class="text-[10px] font-bold uppercase text-gray-400">Shopee warehouse (read-only)</p>
            <p class="font-medium">{{ $sync->shopee_warehouse_name }}</p>
            <p class="mt-1 font-mono text-xs text-gray-600">location {{ $sync->shopee_location_id ?: '—' }} · warehouse id {{ $sync->shopee_warehouse_id }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('shopee.sync.update', $sync->id) }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id', $sync->warehouse_id) }}">

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Aria warehouse</label>
                    @include('jubelio.partials.lookup-combobox', [
                        'endpoint' => $warehouseRoute,
                        'placeholder' => 'Search warehouse...',
                        'hiddenField' => 'warehouse_id',
                        'initialId' => old('warehouse_id', $sync->warehouse_id),
                        'initialName' => $sync->warehouse->name ?? null,
                    ])
                    @error('warehouse_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('shopee.sync.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
