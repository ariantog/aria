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

                <div class="space-y-2">
                    <label for="shop_id" class="block text-sm font-medium text-gray-700">Shopee shop_id</label>
                    <p class="text-xs text-gray-500">Satu baris = satu toko Shopee <em>atau</em> kombinasi toko + gudang pickup Shopee. Toko export (SPMY, SPSG, …): isi <code>shop_id</code> yang sama di <strong>setiap</strong> baris pickup (CITOS / BSD / …) yang memenuhi order itu — Aria gudang ikut baris pickup. Buka ingest pada semua baris export itu (poll sekali per shop). Authorize lewat Omnichannel → Settings.</p>
                    <input type="number" name="shop_id" id="shop_id" min="0" value="{{ old('shop_id', $sync->shop_id) }}"
                        class="h-10 w-full max-w-xs rounded-md border border-gray-300 px-3 text-sm font-mono focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                    @error('shop_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-2">
                    <label for="invoice_prefix" class="block text-sm font-medium text-gray-700">Invoice prefix (antrean Jubelio)</label>
                    <p class="text-xs text-gray-500">FLEXI: SPMY, SPSG, SPTH, SPPH, SPBR. Indonesia tetap SP. Invoice = <code>{prefix}-{ordersn}</code>.</p>
                    <select name="invoice_prefix" id="invoice_prefix"
                        class="h-10 w-full max-w-md rounded-md border border-gray-300 px-3 text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        @foreach($invoicePrefixOptions as $code => $label)
                            <option value="{{ $code }}" @selected(strtoupper((string) old('invoice_prefix', $sync->invoice_prefix ?? 'SP')) === strtoupper($code))>
                                {{ $code }} — {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('invoice_prefix')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="direct_order_ingest" value="1" class="mt-1 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                        @checked(old('direct_order_ingest', $sync->direct_order_ingest ?? true))>
                    <span>
                        <span class="font-medium">Direct order ingest (tanpa Jubelio)</span>
                        <span class="mt-0.5 block text-xs text-gray-500">Cron <code>shopee:poll-orders</code> menarik order untuk shop ini. Aktifkan <code>SHOPEE_ORDER_POLL_ENABLED</code> + <code>SHOPEE_PRECREATE_JUBELIO_QUEUE</code> untuk antrean <code>{prefix}-{ordersn}</code>.</span>
                    </span>
                </label>
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
