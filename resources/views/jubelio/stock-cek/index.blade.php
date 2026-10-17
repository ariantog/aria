@extends('layouts.app')

@section('title', 'Jubelio Cek Stok')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Cek Stok', 'href' => route('jubelio.stock.cek')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div>
        <h1 class="text-2xl font-bold">Jubelio Cek Stok</h1>
        <p class="mt-1 text-sm text-gray-500">
            Ambil respons mentah dari <span class="font-mono text-xs">POST inventory/items/all-stocks</span>
            (sumber yang sama dengan cron stock check). Masukkan <strong>Jubelio item_id</strong>, bukan ID SKU Aria.
        </p>
    </div>

    <div class="max-w-2xl rounded-xl border border-blue-100 bg-blue-50/80 px-4 py-3 text-sm text-blue-900">
        <p class="font-semibold">Parity vs Aria</p>
        <p class="mt-1">
            Stock check membandingkan Aria <code class="rounded bg-white/80 px-1">warehouse_item.qty</code> ke
            <strong>on hand + on order + reserved</strong> (masing-masing ≥ 0). Kolom <code class="rounded bg-white/80 px-1">available</code> hanya referensi.
        </p>
    </div>

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Cari Item</h2>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('jubelio.stock.cek') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1 space-y-2">
                    <label for="item_id" class="block text-sm font-medium text-gray-700">Jubelio item_id</label>
                    <input type="text" name="item_id" id="item_id" required
                           value="{{ $itemId }}"
                           placeholder="contoh: 2851"
                           class="h-10 w-full rounded-lg border border-gray-300 px-3 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           data-testid="jubelio-cek-stock-item-id">
                </div>
                <button type="submit"
                        class="h-10 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800"
                        data-testid="jubelio-cek-stock-submit">
                    Cek
                </button>
            </form>
        </div>
    </div>

    @if($lookupError)
    <div class="max-w-2xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $lookupError }}</div>
    @endif

    @if($payload)
    <div class="max-w-4xl space-y-4 rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="font-semibold">Hasil API</h2>
            @if($ariaItem)
            <p class="mt-1 text-sm text-gray-600">
                Terhubung ke Aria:
                <a href="{{ $ariaItem->showUrl() }}" class="font-medium text-blue-700 hover:underline">{{ $ariaItem->code }}</a>
                (SKU #{{ $ariaItem->id }})
            </p>
            @else
            <p class="mt-1 text-sm text-gray-500">Tidak ada SKU Aria dengan jubelio_item_id ini.</p>
            @endif
        </div>

        @if($locationInterpretation !== [])
        <div class="px-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Interpretasi per lokasi (stock check)</h3>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-[10px] uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">location_id</th>
                            <th class="py-2 pr-4 text-right">on_hand</th>
                            <th class="py-2 pr-4 text-right">on_order</th>
                            <th class="py-2 pr-4 text-right">reserved</th>
                            <th class="py-2 pr-4 text-right">available</th>
                            <th class="py-2 text-right">parity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y font-mono text-xs">
                        @foreach($locationInterpretation as $row)
                        <tr>
                            <td class="py-2 pr-4">{{ $row['location_id'] }}</td>
                            <td class="py-2 pr-4 text-right text-blue-700">{{ format_amount($row['on_hand'], 0) }}</td>
                            <td class="py-2 pr-4 text-right text-orange-700">{{ format_amount($row['on_order'], 0) }}</td>
                            <td class="py-2 pr-4 text-right text-violet-700">{{ format_amount($row['reserved'], 0) }}</td>
                            <td class="py-2 pr-4 text-right text-gray-600">{{ format_amount($row['available'], 0) }}</td>
                            <td class="py-2 text-right font-semibold text-gray-900">{{ format_amount($row['parity_for_aria'], 0) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="border-t border-gray-100 px-6 py-4">
            <pre class="max-h-[32rem] overflow-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400" data-testid="jubelio-cek-stock-json">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
    @endif
</div>
@endsection
