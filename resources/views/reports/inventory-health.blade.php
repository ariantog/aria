@extends('layouts.app')

@section('title', 'Inventory Health')

@section('content')
@php
use App\Enums\ItemType;
use App\Http\Controllers\Reports\InventoryHealthController;
use App\Services\InventoryHealth\InventoryHealthClassifier;

$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Inventory Health', 'href' => route('reports.inventory-health')],
];

$fmtNum = fn ($v) => format_amount($v, 0);
$fmtCover = function (?float $cover) {
    if ($cover === null) {
        return '—';
    }

    return format_amount($cover, 1).'d';
};
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4" data-testid="inventory-health-page">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Inventory Health</h1>
        <p class="mt-0.5 text-sm text-gray-500">
            Net sell quantity (sell minus return) versus current stock.
            Cover under {{ InventoryHealthClassifier::LOW_COVER_DAYS }} days is low stock;
            over {{ InventoryHealthClassifier::OVERSTOCK_COVER_DAYS }} days is overstock.
            Window {{ $windows['period_from'] }} → {{ $windows['period_to'] }}
            ({{ $windows['period_days'] }} days).
        </p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-l-4 border-gray-200 border-l-amber-500 bg-white p-3">
            <p class="text-sm font-medium text-amber-600">Fast / Low Stock</p>
            <p class="mt-1 text-xs text-gray-500">Selling, but cover is under {{ InventoryHealthClassifier::LOW_COVER_DAYS }} days.</p>
        </div>
        <div class="rounded-xl border border-l-4 border-gray-200 border-l-emerald-500 bg-white p-3">
            <p class="text-sm font-medium text-emerald-600">Healthy</p>
            <p class="mt-1 text-xs text-gray-500">Selling, with {{ InventoryHealthClassifier::LOW_COVER_DAYS }}–{{ InventoryHealthClassifier::OVERSTOCK_COVER_DAYS }} days of cover.</p>
        </div>
        <div class="rounded-xl border border-l-4 border-gray-200 border-l-sky-500 bg-white p-3">
            <p class="text-sm font-medium text-sky-600">Overstock</p>
            <p class="mt-1 text-xs text-gray-500">Selling, but cover is over {{ InventoryHealthClassifier::OVERSTOCK_COVER_DAYS }} days.</p>
        </div>
        <div class="rounded-xl border border-l-4 border-gray-200 border-l-gray-500 bg-white p-3">
            <p class="text-sm font-medium text-gray-600">Slow Moving</p>
            <p class="mt-1 text-xs text-gray-500">Stock on hand, sales in 90 days but not in this period.</p>
        </div>
        <div class="rounded-xl border border-l-4 border-gray-200 border-l-rose-500 bg-white p-3">
            <p class="text-sm font-medium text-rose-600">Dead Stock</p>
            <p class="mt-1 text-xs text-gray-500">Stock on hand and no net sales in 90 days.</p>
        </div>
    </div>

    @include('transactions.partials.export-sell-filters', [
        'formAction' => route('reports.inventory-health'),
        'resetUrl' => route('reports.inventory-health'),
        'filters' => $filters,
        'typeOptions' => $typeOptions,
        'selectedType' => $filters['type'] ?? '',
        'perPage' => $perPage,
        'showPartyFilters' => true,
        'showStatusFilter' => true,
        'statusOptions' => $statusOptions,
        'selectedStatus' => $filters['status'] ?? '',
        'defaultOpen' => true,
        'senderLookupUrl' => $senderLookupUrl,
        'receiverLookupUrl' => $receiverLookupUrl,
        'senderLabel' => $senderLabel,
        'receiverLabel' => $receiverLabel,
        'selectedSender' => $selectedSender,
        'selectedReceiver' => $selectedReceiver,
        'itemLookupUrl' => $itemLookupUrl,
        'selectedItem' => $selectedItem,
    ])

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" data-testid="inventory-health-table">
                <thead>
                    <tr class="border-b bg-gray-50 text-left">
                        <th class="px-3 py-2 font-medium text-gray-600">Product</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Sold</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Returned</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Net</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Stock</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Cover</th>
                        <th class="px-3 py-2 font-medium text-gray-600">Last Sold</th>
                        <th class="px-3 py-2 font-medium text-gray-600">Status</th>
                        <th class="px-3 py-2 font-medium text-gray-600">Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $item)
                    @php
                        $health = $item->health;
                        $itemType = $item->type instanceof ItemType ? $item->type : ItemType::tryFrom((int) $item->type);
                    @endphp
                    <tr class="border-b hover:bg-gray-50/50" data-testid="inventory-health-row-{{ $item->id }}">
                        <td class="px-3 py-2">
                            <div class="flex flex-col">
                                <a href="{{ InventoryHealthController::itemShowUrl($itemType, (int) $item->id) }}"
                                   class="font-bold text-blue-600 hover:underline">{{ $item->name }}</a>
                                <span class="font-mono text-xs text-gray-500">{{ $item->code }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtNum($item->sold_period ?? 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtNum($item->returned_period ?? 0) }}</td>
                        <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $fmtNum($item->net_period ?? 0) }}</td>
                        <td class="px-3 py-2 text-right font-bold tabular-nums">{{ $fmtNum($item->current_stock ?? 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtCover($health['days_of_cover'] ?? null) }}</td>
                        <td class="px-3 py-2 text-sm">
                            {{ $item->last_sold_at ? \Illuminate\Support\Carbon::parse($item->last_sold_at)->format('d M Y') : '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs text-white {{ $health['color'] }}"
                                  data-testid="inventory-health-status-{{ $item->id }}">{{ $health['label'] }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-700">{{ $health['rec'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="h-32 text-center text-gray-500">No items match these filters.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $rows, 'label' => 'items'])
    </div>
</div>
@endsection
