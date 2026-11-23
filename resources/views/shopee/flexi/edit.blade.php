@extends('layouts.app')

@section('title', $isCreate ? 'Add FLEXI Shop' : 'Edit FLEXI Shop')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee FLEXI Shops', 'href' => route('shopee.flexi.index')],
    ['title' => $isCreate ? 'Add' : 'Edit', 'href' => '#'],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('shopee.flexi.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">{{ $isCreate ? 'Add FLEXI shop' : 'Edit FLEXI shop' }}</h1>
    </div>

    <div class="max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ $isCreate ? route('shopee.flexi.store') : route('shopee.flexi.update', $row) }}" class="space-y-6">
            @csrf
            @if(! $isCreate)
                @method('PATCH')
            @endif

            <div class="space-y-2">
                <label for="shop_id" class="block text-sm font-medium text-gray-700">Shopee shop_id</label>
                <input type="number" name="shop_id" id="shop_id" min="1" required
                    value="{{ old('shop_id', $row->shop_id ?: '') }}"
                    class="h-10 w-full rounded-md border border-gray-300 px-3 font-mono text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                @error('shop_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-2">
                <label for="invoice_prefix" class="block text-sm font-medium text-gray-700">Invoice prefix</label>
                <select name="invoice_prefix" id="invoice_prefix" class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                    @foreach($invoicePrefixOptions as $code => $label)
                        <option value="{{ $code }}" @selected(strtoupper((string) old('invoice_prefix', $row->invoice_prefix ?? 'SPMY')) === strtoupper($code))>
                            {{ $code }} — {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('invoice_prefix')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-2">
                <label for="label" class="block text-sm font-medium text-gray-700">Label (opsional)</label>
                <input type="text" name="label" id="label" maxlength="255"
                    value="{{ old('label', $row->label) }}"
                    placeholder="mis. Malaysia export"
                    class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="direct_order_ingest" value="1" class="mt-1 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                    @checked(old('direct_order_ingest', $row->direct_order_ingest ?? true))>
                <span>
                    <span class="font-medium">Poll / push ingest</span>
                    <span class="mt-0.5 block text-xs text-gray-500">Sertakan shop ini di <code>shopee:poll-orders</code> bila cron + env aktif.</span>
                </span>
            </label>

            <div class="flex justify-end gap-3">
                <a href="{{ route('shopee.flexi.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection