@php
$whList = $warehouses ?? [];
$sizes = ($block['kind'] ?? '') === 'matrix' ? ($block['sizes'] ?? []) : ['—'];
$hasMultipleSizes = count($sizes) > 1;
@endphp

<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
    @if(! empty($block['title']))
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-800">
            {{ $block['title'] }}
        </div>
    @endif
    <table class="min-w-full divide-y divide-gray-200 text-sm" data-warehouse-compare-table data-testid="warehouse-compare-table-{{ $block['id'] ?? 'block' }}">
        <thead class="bg-gray-50">
            <tr>
                <th rowspan="2" class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-700">Product</th>
                <th rowspan="2" class="px-3 py-2 text-left font-semibold text-gray-700">Color</th>
                @foreach($whList as $warehouse)
                    <th colspan="{{ count($sizes) + ($hasMultipleSizes ? 1 : 0) }}"
                        class="border-l border-gray-200 bg-green-100 px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-green-900">
                        {{ $warehouse['name'] }}
                        @if($loop->first)
                            <span class="block text-[10px] font-normal normal-case text-green-800">(pivot)</span>
                        @endif
                    </th>
                @endforeach
            </tr>
            <tr>
                @foreach($whList as $warehouse)
                    @foreach($sizes as $size)
                        <th class="border-l border-gray-200 bg-green-50 px-2 py-1 text-center text-xs font-medium text-gray-700">
                            {{ $size === '—' ? 'Stock' : $size }}
                        </th>
                    @endforeach
                    @if($hasMultipleSizes)
                        <th class="border-l border-gray-200 bg-green-50 px-2 py-1 text-center text-xs font-medium text-gray-700">Σ</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @foreach($block['rows'] ?? [] as $row)
                @if(($row['_type'] ?? '') === 'section')
                    <tr class="bg-gray-100">
                        <td colspan="{{ 2 + count($whList) * (count($sizes) + ($hasMultipleSizes ? 1 : 0)) }}"
                            class="px-3 py-2 font-semibold text-gray-800">
                            @if(! empty($row['group_url']))
                                <a href="{{ $row['group_url'] }}" class="text-blue-700 hover:underline">{{ $row['name'] }}</a>
                            @else
                                {{ $row['name'] }}
                            @endif
                            <span class="ml-2 text-xs font-normal text-gray-500">{{ $row['pcode'] }}</span>
                        </td>
                    </tr>
                @else
                    <tr @class(['bg-red-50/60' => ! empty($row['is_low_stock'])])>
                        <td class="sticky left-0 z-10 bg-white px-3 py-1.5 text-gray-500">{{ $row['pcode'] ?? '' }}</td>
                        <td class="px-3 py-1.5">
                            @if(! empty($row['color_url']))
                                <a href="{{ $row['color_url'] }}" class="text-blue-700 hover:underline">{{ $row['color_name'] }}</a>
                            @else
                                {{ $row['color_name'] ?? '—' }}
                            @endif
                        </td>
                        @foreach($whList as $warehouse)
                            @php $wid = (int) $warehouse['id']; @endphp
                            @foreach($sizes as $size)
                                @php
                                    $prefix = $size === '—' ? '' : str_replace(['.', ' '], '_', strtolower($size)).'_';
                                    $cells = $row['warehouses'][$prefix] ?? [];
                                    $qty = $cells[$wid] ?? null;
                                @endphp
                                <td class="border-l border-gray-100 px-2 py-1.5 text-center tabular-nums @if($loop->parent->first && ($qty === 0 || $qty === null) && ($sort ?? '') === \App\Services\WarehouseCompare\WarehouseCompareService::SORT_RECOMMENDATION) font-semibold text-red-700 @else text-gray-900 @endif">
                                    {{ $qty === null ? '—' : number_format($qty) }}
                                </td>
                            @endforeach
                            @if($hasMultipleSizes)
                                @php $totals = $row['warehouses']['total_'] ?? []; @endphp
                                <td class="border-l border-gray-100 px-2 py-1.5 text-center font-medium tabular-nums text-gray-900">
                                    {{ isset($totals[$wid]) ? number_format($totals[$wid]) : '—' }}
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
