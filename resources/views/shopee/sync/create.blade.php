@extends('layouts.app')

@section('title', 'Create Shopee Warehouse Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
    ['title' => 'Create', 'href' => route('shopee.sync.create')],
];
$warehouseRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'sender', 'addrbook_type' => $addrbookTypes['warehouse']]);
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('shopee.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Create Shopee Mapping</h1>
    </div>

    @if(! $connectionReady)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Authorize Shopee di <a href="{{ route('shopee-ads.index') }}" class="font-medium underline">Shopee Ads</a> dulu agar daftar gudang Shopee bisa dimuat.
        </div>
    @endif

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        <form method="POST" action="{{ route('shopee.sync.store') }}" class="space-y-6"
              x-data="{
                warehouses: @js($shopeeWarehouses),
                onWarehouseChange(id) {
                    const row = this.warehouses.find(w => String(w.warehouse_id) === String(id));
                    document.getElementById('shopee_warehouse_id').value = row ? row.warehouse_id : '';
                    document.getElementById('shopee_location_id').value = row ? (row.location_id || '') : '';
                    document.getElementById('shopee_warehouse_name').value = row ? (row.warehouse_name || '') : '';
                }
              }">
            @csrf
            <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id') }}">
            <input type="hidden" name="shopee_warehouse_id" id="shopee_warehouse_id" value="{{ old('shopee_warehouse_id') }}">
            <input type="hidden" name="shopee_location_id" id="shopee_location_id" value="{{ old('shopee_location_id') }}">
            <input type="hidden" name="shopee_warehouse_name" id="shopee_warehouse_name" value="{{ old('shopee_warehouse_name') }}">

            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Shopee pickup warehouse</label>
                <select @change="onWarehouseChange($event.target.value)" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm" @disabled(! $connectionReady)>
                    <option value="">Choose Shopee warehouse</option>
                    @foreach($shopeeWarehouses as $wh)
                    <option value="{{ $wh['warehouse_id'] ?? '' }}">{{ ($wh['warehouse_name'] ?? 'Warehouse').' · loc '.($wh['location_id'] ?? '—') }}</option>
                    @endforeach
                </select>
                @error('shopee_warehouse_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Aria warehouse (internal)</label>
                @include('jubelio.partials.lookup-combobox', ['endpoint' => $warehouseRoute, 'placeholder' => 'Search warehouse...', 'hiddenField' => 'warehouse_id'])
                @error('warehouse_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('shopee.sync.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Save mapping</button>
            </div>
        </form>
    </div>
</div>
@endsection
