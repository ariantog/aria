@extends('layouts.app')

@section('title', 'Link Shopee: ' . $item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => $item->name, 'href' => $item->showUrl()],
    ['title' => 'Link Shopee', 'href' => '#'],
];
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold">Cari produk Shopee untuk {{ $item->code }}</h1>
        <a href="{{ route('items.shopee', $item->id) }}" class="text-sm text-gray-500 hover:text-gray-800">← Kembali</a>
    </div>

    <form method="GET" action="{{ route('items.shopee-search', $item->id) }}" class="mb-6 flex gap-2">
        <input type="text" name="q" value="{{ $query }}" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="SKU / nama produk">
        <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Item ID</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">SKU</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($searchResults as $row)
                <tr>
                    <td class="px-4 py-3 font-mono">{{ $row['item_id'] ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $row['item_name'] ?? $row['name'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row['item_sku'] ?? $row['sku'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('items.shopee-link', $item->id) }}">
                            @csrf
                            <input type="hidden" name="shopee_item_id" value="{{ $row['item_id'] ?? '' }}">
                            <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-orange-700">Link</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No results. Try another keyword.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
