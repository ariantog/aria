@php
$whList = $warehouses ?? [];
$sortKey = $sort ?? '';
@endphp

<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 text-sm" data-warehouse-compare-table data-testid="warehouse-compare-list-table">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-3 py-2.5 text-left font-semibold">Item code</th>
                <th class="px-3 py-2.5 text-left font-semibold">Name</th>
                <th class="px-3 py-2.5 text-left font-semibold">Pcode</th>
                <th class="px-3 py-2.5 text-left font-semibold">Color</th>
                <th class="px-3 py-2.5 text-left font-semibold">Size</th>
                <th class="px-3 py-2.5 text-right font-semibold">Sold 12 mo</th>
                @foreach($whList as $warehouse)
                    <th class="border-l border-gray-200 px-3 py-2.5 text-right font-semibold @if($loop->first) bg-green-50 text-green-900 @endif">
                        {{ $warehouse['name'] }}
                        @if($loop->first)
                            <span class="block text-[10px] font-normal normal-case text-green-800">(pivot)</span>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @foreach($block['rows'] ?? [] as $row)
                <tr @class(['bg-red-50/60' => ! empty($row['is_low_stock'])])>
                    <td class="px-3 py-2 font-mono text-xs text-gray-900">
                        <a href="{{ $row['item_url'] }}" class="text-blue-700 hover:underline">{{ $row['code'] }}</a>
                    </td>
                    <td class="max-w-xs truncate px-3 py-2 text-gray-800" title="{{ $row['name'] }}">{{ $row['name'] }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $row['pcode'] }}</td>
                    <td class="px-3 py-2 text-gray-700">{{ $row['color'] }}</td>
                    <td class="px-3 py-2 text-gray-700">{{ $row['size'] }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                        {{ format_amount($row['sold_12m'] ?? 0, 0) }}
                    </td>
                    @foreach($whList as $warehouse)
                        @php
                            $wid = (int) $warehouse['id'];
                            $qty = $row['stocks'][$wid] ?? null;
                        @endphp
                        <td class="border-l border-gray-100 px-3 py-2 text-right tabular-nums @if($loop->first && ($qty === 0 || $qty === null) && $sortKey === \App\Services\WarehouseCompare\WarehouseCompareService::SORT_RECOMMENDATION) font-semibold text-red-700 @else text-gray-900 @endif">
                            {{ $qty === null ? '—' : number_format($qty) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
