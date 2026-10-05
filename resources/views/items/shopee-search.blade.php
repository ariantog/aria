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

    <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
        <p class="font-medium text-gray-900">Cara link ke Shopee</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-gray-600">
            <li><strong>Nama produk</strong> di Seller Center (contoh: <span class="font-mono">knee</span>, <span class="font-mono">kneewrap</span>).</li>
            <li><strong>Shopee item ID</strong> (angka besar dari URL produk / API) — paste angka saja.</li>
            <li><strong>Kode Variasi</strong> (contoh: <span class="font-mono">KNEEWRAP-01-REDIRON</span>) — dicocokkan ke <span class="font-mono">model_sku</span> via scan katalog (satu halaman per pencarian; Auto Link scan penuh via cron).</li>
        </ul>
    </div>

    <form method="GET" action="{{ route('items.shopee-search', $item->id) }}" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ $query }}" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Nama produk, item ID, atau Kode Variasi">
        <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Search</button>
    </form>

    @can(\App\Models\Item::getPermissions()['edit'])
    <form method="POST" action="{{ route('items.shopee-link', $item->id) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4" data-testid="shopee-link-by-id-form">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-500">Shopee item ID</label>
            <input type="number" name="shopee_item_id" min="1" required class="mt-1 w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="844078646">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Model ID (variasi)</label>
            <input type="number" name="shopee_model_id" min="0" class="mt-1 w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="14254207169">
        </div>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Link by ID</button>
        <p class="w-full text-xs text-gray-500">Model ID = variasi di Seller Center (Daftar Variasi). Kosongkan hanya jika produk tanpa variasi.</p>
    </form>
    @endcan

    @if(!empty($searchErrorHint))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="shopee-search-api-error">
            Shopee API: {{ $searchErrorHint }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Item ID</th>
                    <th class="px-4 py-3 text-left">Model ID</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">SKU / Kode Variasi</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($searchResults as $row)
                <tr>
                    <td class="px-4 py-3 font-mono">{{ $row['item_id'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row['model_id'] ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $row['item_name'] ?? $row['name'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row['item_sku'] ?? $row['sku'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('items.shopee-link', $item->id) }}">
                            @csrf
                            <input type="hidden" name="shopee_item_id" value="{{ $row['item_id'] ?? '' }}">
                            @if(!empty($row['model_id']))
                                <input type="hidden" name="shopee_model_id" value="{{ $row['model_id'] }}">
                            @endif
                            <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-orange-700">Link</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    No results. Try product name, Shopee item ID, or exact Kode Variasi. Use <strong>Link by ID</strong> when you have item + model IDs from Seller Center.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
