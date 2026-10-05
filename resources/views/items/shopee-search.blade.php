@extends('layouts.app')

@section('title', 'Link Shopee: ' . $item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => $item->name, 'href' => $item->showUrl()],
    ['title' => 'Link Shopee', 'href' => '#'],
];
$ariaSku = $ariaSku ?? $item->code;
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold">Cari produk Shopee untuk {{ $item->code }}</h1>
        <a href="{{ route('items.shopee', $item->id) }}" class="text-sm text-gray-500 hover:text-gray-800">← Kembali</a>
    </div>

    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
        Link harus ke <strong>Kode Variasi</strong> yang sama dengan SKU Aria: <span class="font-mono font-semibold">{{ $ariaSku }}</span>.
        Parent SKU saja (mis. <span class="font-mono">KNEESUPPORT-21</span>) bukan size/warna — pilih baris variasi yang cocok di bawah.
    </div>

    <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
        <p class="font-medium text-gray-900">Cara cari</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-gray-600">
            <li>Nama produk (contoh: <span class="font-mono">knee</span>, <span class="font-mono">CoreNation</span>).</li>
            <li>Parent pcode: <span class="font-mono">KNEESUPPORT-21</span> atau SKU penuh <span class="font-mono">{{ $ariaSku }}</span>.</li>
            <li>Shopee item ID + model ID (form di bawah).</li>
        </ul>
    </div>

    <form method="GET" action="{{ route('items.shopee-search', $item->id) }}" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ $query }}" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Nama produk, parent SKU, atau Kode Variasi">
        <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Search</button>
    </form>

    @can(\App\Models\Item::getPermissions()['edit'])
    <form method="POST" action="{{ route('items.shopee-link', $item->id) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4" data-testid="shopee-link-by-id-form">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-500">Shopee item ID</label>
            <input type="number" name="shopee_item_id" min="1" required class="mt-1 w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="40623293040">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Model ID (variasi)</label>
            <input type="number" name="shopee_model_id" min="1" required class="mt-1 w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="wajib jika ada variasi">
        </div>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Link by ID</button>
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
                    <th class="px-4 py-3 text-left">Kode Variasi</th>
                    <th class="px-4 py-3 text-left">Parent SKU</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($searchResults as $row)
                @php
                    $exact = !empty($row['exact_match']);
                    $hasModel = !empty($row['model_id']);
                @endphp
                <tr class="{{ $exact ? 'bg-emerald-50' : '' }}" data-testid="{{ $exact ? 'shopee-search-exact-match' : 'shopee-search-row' }}">
                    <td class="px-4 py-3 font-mono">{{ $row['item_id'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row['model_id'] ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $row['item_name'] ?? $row['name'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row['model_sku'] ?? $row['item_sku'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row['parent_item_sku'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($hasModel)
                        <form method="POST" action="{{ route('items.shopee-link', $item->id) }}">
                            @csrf
                            <input type="hidden" name="shopee_item_id" value="{{ $row['item_id'] ?? '' }}">
                            @if(!empty($row['model_id']))
                                <input type="hidden" name="shopee_model_id" value="{{ $row['model_id'] }}">
                            @endif
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-medium text-white {{ $exact ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-orange-600 hover:bg-orange-700' }}">
                                {{ $exact ? 'Link (cocok)' : 'Link' }}
                            </button>
                        </form>
                        @else
                        <span class="text-xs text-gray-400">Buka variasi</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    No results. Try product name, parent <span class="font-mono">KNEESUPPORT-21</span>, or full Kode Variasi <span class="font-mono">{{ $ariaSku }}</span>.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
