@extends('layouts.app')

@section('title', 'Shopee FLEXI Shops')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee FLEXI Shops', 'href' => route('shopee.flexi.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold">Shopee FLEXI / export shops</h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Konfigurasi <code>shop_id</code> dan prefix invoice (<code>SPMY-{ordersn}</code>, dll).
                Posting order export dilakukan di halaman
                <a href="{{ route('shopee.flexi.orders') }}" class="text-orange-700 hover:underline">FLEXI orders</a>.
                Gudang Aria: <a href="{{ route('shopee.sync.index') }}" class="text-orange-700 hover:underline">Warehouse Map</a>.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('shopee.flexi.orders') }}" class="inline-flex rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium text-orange-800 hover:bg-orange-50">
                Buka FLEXI orders
            </a>
            <a href="{{ route('shopee.flexi.create') }}" class="inline-flex rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Add shop
            </a>
        </div>
    </div>

    @if(! empty($authorizedShopIds))
        <p class="text-xs text-gray-600">
            OAuth shop IDs: <span class="font-mono">{{ implode(', ', array_map('strval', $authorizedShopIds)) }}</span>
            — cocokkan dengan Seller Centre lalu simpan di bawah.
        </p>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold uppercase text-gray-600">
                <tr>
                    <th class="px-6 py-4">shop_id</th>
                    <th class="px-6 py-4">Prefix</th>
                    <th class="px-6 py-4">Label</th>
                    <th class="px-6 py-4">Poll shadow</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-mono text-xs">{{ $row->shop_id }}</td>
                    <td class="px-6 py-4 font-mono font-medium">{{ strtoupper($row->invoice_prefix) }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $row->label ?: '—' }}</td>
                    <td class="px-6 py-4">{{ $row->direct_order_ingest ? 'Ya' : '—' }}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('shopee.flexi.edit', $row) }}" class="text-orange-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('shopee.flexi.delete', $row) }}" class="ml-3 inline" onsubmit="return confirm('Delete?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Belum ada mapping. Tambahkan satu baris per toko export (MY, SG, …).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection