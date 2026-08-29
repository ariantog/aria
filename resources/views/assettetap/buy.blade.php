@extends('layouts.app')

@section('title', 'Record Buy — '.$item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Asset Tetap', 'href' => route('assettetap.index')],
    ['title' => $item->name, 'href' => route('assettetap.show', $item)],
    ['title' => 'Record Buy', 'href' => route('assettetap.buy', $item)],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Record Buy</h2>
        <p class="mt-0.5 text-sm text-gray-500">Mencatat pembelian {{ $item->name }} (qty 1) ke gudang. Tidak mengedit transaksi lama.</p>
    </div>

    @if($errors->any())
    <div class="max-w-xl rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('assettetap.buy.store', $item) }}" class="max-w-xl space-y-5" data-testid="assettetap-buy-form">
        @csrf
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div class="space-y-1.5">
                <label for="date" class="text-sm font-medium text-gray-700">Tanggal beli</label>
                <input type="date" id="date" name="date" required min="{{ $minDate }}"
                       value="{{ old('date', now()->toDateString()) }}"
                       data-testid="assettetap-buy-date"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-1.5">
                <label for="supplier_id" class="text-sm font-medium text-gray-700">Supplier</label>
                <select id="supplier_id" name="supplier_id" required data-testid="assettetap-buy-supplier"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Pilih supplier</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="warehouse_id" class="text-sm font-medium text-gray-700">Gudang penerima</label>
                <select id="warehouse_id" name="warehouse_id" required data-testid="assettetap-buy-warehouse"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Pilih gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $register->warehouse_id ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="buy_price" class="text-sm font-medium text-gray-700">Harga perolehan</label>
                <input type="number" id="buy_price" name="buy_price" min="0.01" step="0.01" required
                       value="{{ old('buy_price') }}" data-testid="assettetap-buy-price"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-1.5">
                <label for="invoice" class="text-sm font-medium text-gray-700">Invoice <span class="font-normal text-gray-400">(opsional)</span></label>
                <input type="text" id="invoice" name="invoice" value="{{ old('invoice') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-1.5">
                <label for="notes" class="text-sm font-medium text-gray-700">Catatan</label>
                <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" data-testid="assettetap-buy-submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Catat pembelian</button>
            <a href="{{ route('assettetap.show', $item) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600">Cancel</a>
        </div>
    </form>
</div>
@endsection
