@extends('layouts.app')

@section('title', 'Link TikTok Shop: ' . $item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => $item->name, 'href' => $item->showUrl()],
    ['title' => 'Link TikTok Shop', 'href' => '#'],
];
$ariaSku = $ariaSku ?? $item->code;
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold">Cari produk TikTok Shop / Tokopedia untuk {{ $item->code }}</h1>
        <a href="{{ route('items.tiktok-shop', $item->id) }}" class="text-sm text-gray-500 hover:text-gray-800">← Kembali</a>
    </div>

    <div class="mb-6 rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950">
        Link harus ke <strong>seller_sku</strong> yang sama dengan SKU Aria: <span class="font-mono font-semibold">{{ $ariaSku }}</span>.
    </div>

    <form method="GET" action="{{ route('items.tiktok-shop-search', $item->id) }}" class="mb-4 flex flex-wrap gap-2">
        <input type="hidden" name="shop_cipher" value="{{ $shopCipher ?? '' }}">
        <input type="text" name="q" value="{{ $query }}" class="min-w-[12rem] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="seller_sku, kode Aria, atau legacy_code">
        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">Search</button>
    </form>

    @can(\App\Models\Item::getPermissions()['edit'])
    <form method="POST" action="{{ route('items.tiktok-shop-link', $item->id) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4" data-testid="tiktok-shop-link-by-id-form">
        @csrf
        <input type="hidden" name="shop_cipher" value="{{ $shopCipher ?? '' }}">
        <div>
            <label class="block text-xs font-medium text-gray-500">Product ID</label>
            <input type="text" name="tiktok_shop_product_id" required class="mt-1 w-52 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="7490…">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">SKU ID</label>
            <input type="text" name="tiktok_shop_sku_id" required class="mt-1 w-52 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="7491…">
        </div>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Link by ID</button>
    </form>
    @endcan

    @if(!empty($searchErrorHint))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="tiktok-shop-search-api-error">
            TikTok API: {{ $searchErrorHint }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Product ID</th>
                    <th class="px-4 py-3 text-left">SKU ID</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">seller_sku</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($searchResults as $row)
                @php
                    $exact = !empty($row['exact_match']);
                @endphp
                <tr class="{{ $exact ? 'bg-emerald-50' : '' }}" data-testid="{{ $exact ? 'tiktok-shop-search-exact-match' : 'tiktok-shop-search-row' }}">
                    <td class="px-4 py-3 font-mono text-xs break-all">{{ $row['product_id'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs break-all">{{ $row['sku_id'] ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $row['product_name'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row['seller_sku'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('items.tiktok-shop-link', $item->id) }}">
                            @csrf
                            <input type="hidden" name="shop_cipher" value="{{ $shopCipher ?? '' }}">
                            <input type="hidden" name="tiktok_shop_product_id" value="{{ $row['product_id'] ?? '' }}">
                            <input type="hidden" name="tiktok_shop_sku_id" value="{{ $row['sku_id'] ?? '' }}">
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-medium text-white {{ $exact ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-violet-600 hover:bg-violet-700' }}">
                                {{ $exact ? 'Link (cocok)' : 'Link' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    No results. Try full seller_sku <span class="font-mono">{{ $ariaSku }}</span> or paste Product/SKU ID above.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection