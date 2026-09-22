@extends('layouts.app')

@section('title', 'Item age: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/item-age';
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => \App\Models\Addrbook::typeIndexRoute($addrbook->type_slug)],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Item age', 'href' => $baseUrl],
];
$currentSort = $filters['sort'] ?? 'agedesc';
$sortLink = function (string $sort) use ($filters, $baseUrl) {
    $query = array_merge($filters, ['sort' => $sort]);

    return $baseUrl . '?' . http_build_query(array_filter(
        $query,
        fn ($value) => $value !== null && $value !== '',
    ));
};
$showZero = ($filters['show0'] ?? '') === 'show';
$healthBase = route('reports.inventory-health');
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Warehouse item age</h1>
            <p class="text-sm text-gray-500">
                Calendar days since the last completed <span class="font-medium">Buy</span> or <span class="font-medium">Move</span> into
                <span class="text-blue-600">{{ $addrbook->name }}</span> (per SKU with stock here).
            </p>
            <p class="mt-2 max-w-3xl text-xs text-gray-500">
                Uses full transaction history for inbound dates. Book closing only limits new/edited transactions
                (from {{ $bookClosingMinDate->translatedFormat('d M Y') }} onward) — it does not hide older inbound lines.
                For sales velocity / days of cover, use
                <a href="{{ $healthBase }}?warehouse_id={{ $addrbook->id }}" class="font-medium text-blue-600 hover:underline">Inventory Health</a>.
            </p>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'item-age'])

    <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <input type="hidden" name="sort" value="{{ $currentSort }}">
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="show0" value="show" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked($showZero)>
            Show zero stock
        </label>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Apply</button>
        <a href="{{ $baseUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="warehouse-item-age-table">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3"><a href="{{ $sortLink('nameasc') }}" class="hover:text-gray-800">Product</a></th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('codeasc') }}" class="hover:text-gray-800">SKU</a></th>
                    <th class="px-4 py-3 text-right"><a href="{{ $sortLink('qtydesc') }}" class="hover:text-gray-800">Stock</a></th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('inbounddesc') }}" class="hover:text-gray-800">Last inbound</a></th>
                    <th class="px-4 py-3 text-right"><a href="{{ $sortLink('agedesc') }}" class="hover:text-gray-800">Age (days)</a></th>
                    <th class="px-4 py-3">Cover</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php
                        $meta = $ageMeta[$item->id] ?? ['last_inbound_date' => null, 'age_days' => null];
                        $healthUrl = $healthBase . '?' . http_build_query([
                            'warehouse_id' => $addrbook->id,
                            'item_id' => $item->id,
                        ]);
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-2.5">
                            <a href="{{ $item->showUrl() }}" class="font-medium text-blue-600 hover:underline">{{ $item->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $item->code }}</td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ format_amount($item->warehouse_qty ?? 0, 0) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">
                            {{ $meta['last_inbound_date'] ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums text-gray-900">
                            @if($meta['age_days'] !== null)
                                {{ number_format($meta['age_days'], 0) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <a href="{{ $healthUrl }}" class="text-xs font-medium text-blue-600 hover:underline">Health</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No stocked SKUs match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($items->hasPages())
        <div class="text-sm text-gray-600">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection
