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
$currentSort = $filters['sort'] ?? 'staledesc';
$sortLink = function (string $sort) use ($filters, $baseUrl) {
    $query = array_merge($filters, ['sort' => $sort]);

    return $baseUrl . '?' . http_build_query(array_filter(
        $query,
        fn ($value) => $value !== null && $value !== '',
    ));
};
$showZero = ($filters['show0'] ?? '') === 'show';
$staleOnly = filter_var($filters['stale_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
$staleMonths = (int) ($staleMonths ?? \App\Services\Warehouse\WarehouseItemAgeService::DEFAULT_STALE_MONTHS);
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
                Per SKU at <span class="text-blue-600">{{ $addrbook->name }}</span>:
                when stock last came in (Buy/Move) and when it last sold out of this gudang (Sell).
            </p>
            <p class="mt-2 max-w-3xl text-xs text-gray-500">
                <span class="font-medium text-gray-700">Stale</span> = last inbound at least {{ $staleMonths }} months ago
                and no sell from this warehouse in the last {{ $staleMonths }} months (full history; not limited by book closing).
                For velocity / days of cover, use
                <a href="{{ $healthBase }}?warehouse_id={{ $addrbook->id }}" class="font-medium text-blue-600 hover:underline">Inventory Health</a>.
            </p>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'item-age'])

    <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <input type="hidden" name="sort" value="{{ $currentSort }}">
        <div class="flex flex-col gap-1">
            <label for="stale_months" class="text-xs font-medium uppercase text-gray-500">Stale window (months)</label>
            <input id="stale_months" type="number" name="stale_months" min="1" max="36" value="{{ $staleMonths }}"
                class="w-24 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <label class="inline-flex cursor-pointer items-center gap-2 pt-5 text-sm text-gray-700">
            <input type="checkbox" name="stale_only" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked($staleOnly)>
            Stale only
        </label>
        <label class="inline-flex cursor-pointer items-center gap-2 pt-5 text-sm text-gray-700">
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
                    <th class="px-4 py-3 text-right"><a href="{{ $sortLink('agedesc') }}" class="hover:text-gray-800">Inbound age</a></th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('solddesc') }}" class="hover:text-gray-800">Last sold</a></th>
                    <th class="px-4 py-3 text-right">Since sell</th>
                    <th class="px-4 py-3"><a href="{{ $sortLink('staledesc') }}" class="hover:text-gray-800">Stale</a></th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php
                        $meta = $ageMeta[$item->id] ?? [];
                        $healthUrl = $healthBase . '?' . http_build_query([
                            'warehouse_id' => $addrbook->id,
                            'item_id' => $item->id,
                        ]);
                    @endphp
                    <tr class="hover:bg-gray-50/80 @if($meta['stale_unsold'] ?? false) bg-amber-50/60 @endif">
                        <td class="px-4 py-2.5">
                            <a href="{{ $item->showUrl() }}" class="font-medium text-blue-600 hover:underline">{{ $item->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $item->code }}</td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ format_amount($item->warehouse_qty ?? 0, 0) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $meta['last_inbound_date'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums text-gray-900">
                            @if(($meta['inbound_age_days'] ?? null) !== null)
                                {{ number_format($meta['inbound_age_days'], 0) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $meta['last_sold_date'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums text-gray-700">
                            @if(($meta['days_since_sell'] ?? null) !== null)
                                {{ number_format($meta['days_since_sell'], 0) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            @if($meta['stale_unsold'] ?? false)
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Yes</span>
                            @elseif($meta['no_sell_in_window'] ?? false)
                                <span class="text-xs text-gray-500">No recent sell</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <a href="{{ $healthUrl }}" class="text-xs font-medium text-blue-600 hover:underline">Health</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">No stocked SKUs match this filter.</td>
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
